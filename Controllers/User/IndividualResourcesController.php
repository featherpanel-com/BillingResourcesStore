<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\billingresourcesstore\Controllers\User;

use App\App;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\BillingHelper;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingresources\Helpers\ResourcesHelper;
use App\Addons\billingresourcesstore\Helpers\SettingsHelper;
use App\Addons\billingresourcesstore\Chat\IndividualResource;

#[OA\Tag(name: 'User - Billing Resources Store Individual', description: 'User individual resource purchase endpoints')]
class IndividualResourcesController
{
    #[OA\Get(
        path: '/api/user/billingresourcesstore/individual-resources',
        summary: 'Get individual resource prices',
        description: 'Get prices for purchasing individual resources',
        tags: ['User - Billing Resources Store Individual'],
        responses: [
            new OA\Response(response: 200, description: 'Resource prices retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 503, description: 'Individual purchases disabled'),
        ]
    )]
    public function getResourcePrices(Request $request): Response
    {
        try {
            $resources = IndividualResource::getAll(true);

            // Calculate prices with discounts
            $resourcesWithPrices = [];
            foreach ($resources as $resource) {
                $priceCalc = IndividualResource::calculatePriceWithDiscount($resource, 1);
                $resourcesWithPrices[] = [
                    'id' => (int) $resource['id'],
                    'name' => $resource['name'],
                    'description' => $resource['description'],
                    'resource_type' => $resource['resource_type'],
                    'unit' => $resource['unit'],
                    'price_per_unit' => (int) $resource['price_per_unit'],
                    'final_price_per_unit' => $priceCalc['final_price'],
                    'discount_applied' => $priceCalc['discount_applied'],
                    'minimum_amount' => (int) $resource['minimum_amount'],
                    'maximum_amount' => $resource['maximum_amount'] !== null ? (int) $resource['maximum_amount'] : null,
                ];
            }

            return ApiResponse::success([
                'resources' => $resourcesWithPrices,
                'enabled' => true,
            ], 'Resource prices retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get resource prices: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve resource prices: ' . $e->getMessage(), 'GET_PRICES_FAILED', 500);
        }
    }

    #[OA\Post(
        path: '/api/user/billingresourcesstore/individual-resources/purchase',
        summary: 'Purchase individual resources',
        description: 'Purchase individual resources using credits',
        tags: ['User - Billing Resources Store Individual'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resource_type', type: 'string', description: 'Resource type (memory_limit, cpu_limit, etc.)'),
                    new OA\Property(property: 'amount', type: 'integer', description: 'Amount to purchase'),
                ],
                required: ['resource_type', 'amount']
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Resources purchased successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Insufficient credits'),
            new OA\Response(response: 503, description: 'Individual purchases disabled'),
        ]
    )]
    public function purchaseResources(Request $request): Response
    {
        $user = $request->get('user');
        $userId = (int) $user['id'];

        if (!SettingsHelper::isIndividualPurchasesEnabled()) {
            return ApiResponse::error('Individual resource purchases are disabled', 'INDIVIDUAL_PURCHASES_DISABLED', 503);
        }

        $payload = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        $resourceId = isset($payload['resource_id']) ? (int) $payload['resource_id'] : 0;
        $amount = isset($payload['amount']) ? (int) $payload['amount'] : 0;

        if ($resourceId <= 0) {
            return ApiResponse::error('Invalid resource ID', 'INVALID_RESOURCE_ID', 400);
        }

        $resource = IndividualResource::getById($resourceId);
        if ($resource === null || !((bool) ($resource['enabled'] ?? false))) {
            return ApiResponse::error('Resource not found or disabled', 'RESOURCE_NOT_FOUND', 404);
        }

        $minimum = (int) ($resource['minimum_amount'] ?? 1);
        if ($amount < $minimum) {
            return ApiResponse::error(
                "Minimum purchase amount is {$minimum} {$resource['unit']}",
                'BELOW_MINIMUM',
                400
            );
        }

        $maximum = $resource['maximum_amount'] !== null ? (int) $resource['maximum_amount'] : null;
        if ($maximum !== null && $amount > $maximum) {
            return ApiResponse::error(
                "Maximum purchase amount is {$maximum} {$resource['unit']}",
                'ABOVE_MAXIMUM',
                400
            );
        }

        try {
            // Calculate price with discount
            $priceCalc = IndividualResource::calculatePriceWithDiscount($resource, $amount);
            $totalPrice = $priceCalc['final_price'];
            $pricePerUnit = $priceCalc['final_price'] / $amount; // Effective price per unit after discount

            // Check user credits
            $userCredits = CreditsHelper::getUserCredits($userId);
            if ($userCredits < $totalPrice) {
                return ApiResponse::error(
                    'Insufficient credits. Required: ' . $totalPrice . ', Available: ' . $userCredits,
                    'INSUFFICIENT_CREDITS',
                    403
                );
            }

            // Remove credits
            if (!CreditsHelper::removeUserCredits($userId, $totalPrice)) {
                return ApiResponse::error('Failed to deduct credits', 'CREDIT_DEDUCTION_FAILED', 500);
            }

            // Ensure user has resources record
            ResourcesHelper::ensureUserResources($userId);

            // Convert amount based on unit (e.g., GB to MB for memory)
            $resourceType = $resource['resource_type'];
            $unit = $resource['unit'];
            $amountToAdd = $amount;

            // Convert to base unit if needed
            if ($resourceType === 'memory_limit' || $resourceType === 'disk_limit') {
                if ($unit === 'GB') {
                    $amountToAdd = $amount * 1024; // Convert GB to MB
                }
            }

            // Add resources
            if (!ResourcesHelper::addUserResource($userId, $resourceType, $amountToAdd)) {
                // Rollback: refund credits if resource addition fails
                CreditsHelper::addUserCredits($userId, $totalPrice);
                App::getInstance(true)->getLogger()->error("Failed to add resource $resourceType to user $userId");

                return ApiResponse::error('Failed to add resources', 'RESOURCE_ADDITION_FAILED', 500);
            }

            // Generate invoice if enabled for individual resources
            $invoiceId = null;
            if (SettingsHelper::shouldGenerateInvoiceForIndividual()) {
                try {
                    if (BillingHelper::canCreateInvoice($userId)) {
                        $invoiceItems = [];
                        $description = $resource['name'];
                        if (!empty($resource['description'])) {
                            $description .= ' - ' . $resource['description'];
                        }
                        $description .= " ({$amount} {$unit})";

                        $invoiceItems[] = [
                            'description' => $description,
                            'quantity' => (float) $amount,
                            'unit_price' => (float) $pricePerUnit,
                            'total' => (float) $totalPrice,
                        ];

                        $invoiceData = [
                            'status' => 'paid',
                            'tax_rate' => 0.00,
                            'notes' => 'Individual Resource Purchase',
                        ];

                        $invoice = BillingHelper::createInvoiceWithItems($userId, $invoiceData, $invoiceItems);
                        if ($invoice !== null) {
                            $invoiceId = $invoice['id'];
                        }
                    }
                } catch (\Exception $e) {
                    App::getInstance(true)->getLogger()->error('Failed to create invoice for individual resource purchase: ' . $e->getMessage());
                }
            }

            // Get updated credits
            $updatedCredits = CreditsHelper::getUserCredits($userId);

            return ApiResponse::success([
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
                'amount' => $amount,
                'unit' => $unit,
                'price_paid' => $totalPrice,
                'price_per_unit' => $pricePerUnit,
                'discount_applied' => $priceCalc['discount_applied'],
                'credits_remaining' => $updatedCredits,
                'invoice_id' => $invoiceId,
            ], 'Resources purchased successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to purchase resources: ' . $e->getMessage());

            return ApiResponse::error('Failed to purchase resources: ' . $e->getMessage(), 'PURCHASE_FAILED', 500);
        }
    }
}
