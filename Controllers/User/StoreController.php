<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingresourcesstore\Controllers\User;

use App\App;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\BillingHelper;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingresourcesstore\Chat\Purchase;
use App\Addons\billingresources\Helpers\ResourcesHelper;
use App\Addons\billingresourcesstore\Chat\ResourcePackage;
use App\Addons\billingresourcesstore\Helpers\SettingsHelper;

#[OA\Tag(name: 'User - Billing Resources Store', description: 'User resource store endpoints')]
class StoreController
{
    #[OA\Get(
        path: '/api/user/billingresourcesstore/packages',
        summary: 'Get available resource packages',
        description: 'Get all enabled resource packages available for purchase',
        tags: ['User - Billing Resources Store'],
        responses: [
            new OA\Response(response: 200, description: 'Packages retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getPackages(Request $request): Response
    {
        try {
            // Check if store is enabled
            if (!SettingsHelper::isStoreEnabled()) {
                return ApiResponse::error(
                    SettingsHelper::getMaintenanceMessage(),
                    'STORE_DISABLED',
                    503
                );
            }

            $packages = ResourcePackage::getEnabledPackages();

            // Calculate prices with discounts for each package
            $packagesWithPrices = [];
            foreach ($packages as $package) {
                $basePrice = (int) ($package['price'] ?? 0);
                $packageDiscount = null;

                // Check if package has active discount
                if (isset($package['discount_enabled']) && (int) $package['discount_enabled'] === 1) {
                    $discountPercentage = (float) ($package['discount_percentage'] ?? 0);
                    $startDate = $package['discount_start_date'] ?? null;
                    $endDate = $package['discount_end_date'] ?? null;
                    $now = new \DateTime();

                    $isActive = true;
                    try {
                        if ($startDate) {
                            $start = new \DateTime($startDate);
                            if ($now < $start) {
                                $isActive = false;
                            }
                        }
                        if ($endDate && $isActive) {
                            $end = new \DateTime($endDate);
                            if ($now > $end) {
                                $isActive = false;
                            }
                        }
                    } catch (\Exception $e) {
                        $isActive = false;
                    }

                    if ($isActive && $discountPercentage > 0) {
                        $packageDiscount = $discountPercentage;
                    }
                }

                $priceCalculation = SettingsHelper::calculatePriceWithDiscounts($basePrice, $packageDiscount);
                $package['original_price'] = $priceCalculation['original_price'];
                $package['final_price'] = $priceCalculation['final_price'];
                $package['discount_applied'] = $priceCalculation['discount_applied'];

                $packagesWithPrices[] = $package;
            }

            return ApiResponse::success([
                'packages' => array_values($packagesWithPrices),
            ], 'Packages retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get packages: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve packages: ' . $e->getMessage(), 'GET_PACKAGES_FAILED', 500);
        }
    }

    #[OA\Post(
        path: '/api/user/billingresourcesstore/purchase',
        summary: 'Purchase a resource package',
        description: 'Purchase a resource package using credits',
        tags: ['User - Billing Resources Store'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'package_id', type: 'integer', description: 'Package ID to purchase'),
                ],
                required: ['package_id']
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Package purchased successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Insufficient credits'),
            new OA\Response(response: 404, description: 'Package not found'),
        ]
    )]
    public function purchasePackage(Request $request): Response
    {
        $user = $request->get('user');
        $userId = (int) $user['id'];

        $payload = json_decode($request->getContent() ?: '[]', true, 32);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        $packageId = isset($payload['package_id']) ? (int) $payload['package_id'] : 0;
        if ($packageId <= 0) {
            return ApiResponse::error('Invalid package ID', 'INVALID_PACKAGE_ID', 400);
        }

        try {
            // Get package
            $package = ResourcePackage::getById($packageId);
            if (!$package) {
                return ApiResponse::error('Package not found', 'PACKAGE_NOT_FOUND', 404);
            }

            // Check if package is enabled
            if (!isset($package['enabled']) || (int) $package['enabled'] !== 1) {
                return ApiResponse::error('Package is not available', 'PACKAGE_DISABLED', 403);
            }

            // Check if store is enabled
            if (!SettingsHelper::isStoreEnabled()) {
                return ApiResponse::error(
                    SettingsHelper::getMaintenanceMessage(),
                    'STORE_DISABLED',
                    503
                );
            }

            $basePrice = (int) ($package['price'] ?? 0);
            if ($basePrice <= 0) {
                return ApiResponse::error('Package has invalid price', 'INVALID_PACKAGE_PRICE', 400);
            }

            // Calculate price with discounts
            $packageDiscount = null;
            if (isset($package['discount_enabled']) && (int) $package['discount_enabled'] === 1) {
                $discountPercentage = (float) ($package['discount_percentage'] ?? 0);
                $startDate = $package['discount_start_date'] ?? null;
                $endDate = $package['discount_end_date'] ?? null;
                $now = new \DateTime();

                $isActive = true;
                try {
                    if ($startDate) {
                        $start = new \DateTime($startDate);
                        if ($now < $start) {
                            $isActive = false;
                        }
                    }
                    if ($endDate && $isActive) {
                        $end = new \DateTime($endDate);
                        if ($now > $end) {
                            $isActive = false;
                        }
                    }
                } catch (\Exception $e) {
                    $isActive = false;
                }

                if ($isActive && $discountPercentage > 0) {
                    $packageDiscount = $discountPercentage;
                }
            }

            $priceCalculation = SettingsHelper::calculatePriceWithDiscounts($basePrice, $packageDiscount);
            $finalPrice = $priceCalculation['final_price'];
            $discountApplied = $priceCalculation['discount_applied'];

            // Check user credits
            $userCredits = CreditsHelper::getUserCredits($userId);
            if ($userCredits < $finalPrice) {
                return ApiResponse::error(
                    'Insufficient credits. Required: ' . $finalPrice . ', Available: ' . $userCredits,
                    'INSUFFICIENT_CREDITS',
                    403
                );
            }

            // Remove credits (use final price after discount)
            if (!CreditsHelper::removeUserCredits($userId, $finalPrice)) {
                return ApiResponse::error('Failed to deduct credits', 'CREDIT_DEDUCTION_FAILED', 500);
            }

            // Add resources to user
            $resources = [
                'memory_limit' => (int) ($package['memory_limit'] ?? 0),
                'cpu_limit' => (int) ($package['cpu_limit'] ?? 0),
                'disk_limit' => (int) ($package['disk_limit'] ?? 0),
                'server_limit' => (int) ($package['server_limit'] ?? 0),
                'database_limit' => (int) ($package['database_limit'] ?? 0),
                'backup_limit' => (int) ($package['backup_limit'] ?? 0),
                'allocation_limit' => (int) ($package['allocation_limit'] ?? 0),
            ];

            // Only add resources that are > 0
            $resourcesToAdd = [];
            foreach ($resources as $type => $amount) {
                if ($amount > 0) {
                    $resourcesToAdd[$type] = $amount;
                }
            }

            if (!empty($resourcesToAdd)) {
                // Ensure user has resources record
                ResourcesHelper::ensureUserResources($userId);

                // Add each resource
                foreach ($resourcesToAdd as $type => $amount) {
                    if (!ResourcesHelper::addUserResource($userId, $type, $amount)) {
                        // Rollback: refund credits if resource addition fails
                        CreditsHelper::addUserCredits($userId, $finalPrice);
                        App::getInstance(true)->getLogger()->error("Failed to add resource $type to user $userId");

                        return ApiResponse::error('Failed to add resources', 'RESOURCE_ADDITION_FAILED', 500);
                    }
                }
            }

            // Record purchase (store original price, not discounted)
            $purchaseData = [
                'user_id' => $userId,
                'package_id' => $packageId,
                'price' => $finalPrice, // Store final price paid
                'memory_limit' => $resources['memory_limit'],
                'cpu_limit' => $resources['cpu_limit'],
                'disk_limit' => $resources['disk_limit'],
                'server_limit' => $resources['server_limit'],
                'database_limit' => $resources['database_limit'],
                'backup_limit' => $resources['backup_limit'],
                'allocation_limit' => $resources['allocation_limit'],
            ];

            $purchaseId = Purchase::create($purchaseData);
            if ($purchaseId === false) {
                App::getInstance(true)->getLogger()->warning("Failed to record purchase for user $userId, package $packageId");
            }

            // Generate invoice if enabled
            $invoiceId = null;
            if (SettingsHelper::shouldGenerateInvoiceForPackages()) {
                try {
                    if (BillingHelper::canCreateInvoice($userId)) {
                        $invoiceItems = [];
                        $description = $package['name'];
                        if (!empty($package['description'])) {
                            $description .= ' - ' . $package['description'];
                        }

                        $invoiceItems[] = [
                            'description' => $description,
                            'quantity' => 1.00,
                            'unit_price' => (float) $finalPrice,
                            'total' => (float) $finalPrice,
                        ];

                        $invoiceData = [
                            'status' => 'paid',
                            'tax_rate' => 0.00,
                            'notes' => 'Resource Package Purchase',
                        ];

                        $invoice = BillingHelper::createInvoiceWithItems($userId, $invoiceData, $invoiceItems);
                        if ($invoice !== null) {
                            $invoiceId = $invoice['id'];
                        }
                    }
                } catch (\Exception $e) {
                    App::getInstance(true)->getLogger()->error('Failed to create invoice for package purchase: ' . $e->getMessage());
                }
            }

            // Get updated credits
            $updatedCredits = CreditsHelper::getUserCredits($userId);

            return ApiResponse::success([
                'package' => $package,
                'resources_added' => $resourcesToAdd,
                'credits_remaining' => $updatedCredits,
                'price_paid' => $finalPrice,
                'original_price' => $basePrice,
                'discount_applied' => $discountApplied,
                'invoice_id' => $invoiceId,
            ], 'Package purchased successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to purchase package: ' . $e->getMessage());

            return ApiResponse::error('Failed to purchase package: ' . $e->getMessage(), 'PURCHASE_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/user/billingresourcesstore/purchases',
        summary: 'Get user purchase history',
        description: 'Get purchase history for the current user',
        tags: ['User - Billing Resources Store'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Purchases retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getPurchases(Request $request): Response
    {
        $user = $request->get('user');
        $userId = (int) $user['id'];

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));

        try {
            $purchases = Purchase::getByUserId($userId, $page, $limit);
            $total = Purchase::countByUserId($userId);

            return ApiResponse::success([
                'purchases' => array_values($purchases),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int) ceil($total / $limit),
                ],
            ], 'Purchases retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get purchases: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve purchases: ' . $e->getMessage(), 'GET_PURCHASES_FAILED', 500);
        }
    }
}
