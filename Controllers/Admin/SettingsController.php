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

namespace App\Addons\billingresourcesstore\Controllers\Admin;

use App\App;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingresourcesstore\Helpers\SettingsHelper;

#[OA\Tag(name: 'Admin - Billing Resources Store Settings', description: 'Admin store settings management endpoints')]
class SettingsController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesstore/settings',
        summary: 'Get store settings',
        description: 'Get all store settings',
        tags: ['Admin - Billing Resources Store Settings'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        try {
            $settings = SettingsHelper::getAllSettings();

            return ApiResponse::success([
                'settings' => $settings,
            ], 'Settings retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get settings: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve settings: ' . $e->getMessage(), 'GET_SETTINGS_FAILED', 500);
        }
    }

    #[OA\Put(
        path: '/api/admin/billingresourcesstore/settings',
        summary: 'Update store settings',
        description: 'Update store settings',
        tags: ['Admin - Billing Resources Store Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'store_enabled', type: 'boolean'),
                    new OA\Property(property: 'maintenance_message', type: 'string'),
                    new OA\Property(property: 'global_discount', type: 'number'),
                    new OA\Property(property: 'minimum_purchase_for_discount', type: 'integer'),
                    new OA\Property(property: 'bulk_discounts', type: 'object'),
                    new OA\Property(property: 'max_discount', type: 'number'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $payload = json_decode($request->getContent() ?: '[]', true, 32);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        try {
            if (isset($payload['store_enabled'])) {
                SettingsHelper::setStoreEnabled((bool) $payload['store_enabled']);
            }

            if (isset($payload['individual_purchases_enabled'])) {
                SettingsHelper::setIndividualPurchasesEnabled((bool) $payload['individual_purchases_enabled']);
            }

            if (isset($payload['maintenance_message'])) {
                SettingsHelper::setMaintenanceMessage((string) $payload['maintenance_message']);
            }

            if (isset($payload['global_discount'])) {
                SettingsHelper::setGlobalDiscount((float) $payload['global_discount']);
            }

            if (isset($payload['minimum_purchase_for_discount'])) {
                SettingsHelper::setMinimumPurchaseForDiscount((int) $payload['minimum_purchase_for_discount']);
            }

            if (isset($payload['bulk_discounts']) && is_array($payload['bulk_discounts'])) {
                SettingsHelper::setBulkDiscounts($payload['bulk_discounts']);
            }

            if (isset($payload['max_discount'])) {
                SettingsHelper::setMaxDiscount((float) $payload['max_discount']);
            }

            if (isset($payload['front_page_display'])) {
                SettingsHelper::setFrontPageDisplay((string) $payload['front_page_display']);
            }

            if (isset($payload['invoice_generation_enabled'])) {
                SettingsHelper::setInvoiceGenerationEnabled((bool) $payload['invoice_generation_enabled']);
            }

            if (isset($payload['invoice_generation_packages'])) {
                SettingsHelper::setInvoiceGenerationForPackages((bool) $payload['invoice_generation_packages']);
            }

            if (isset($payload['invoice_generation_individual'])) {
                SettingsHelper::setInvoiceGenerationForIndividual((bool) $payload['invoice_generation_individual']);
            }

            $settings = SettingsHelper::getAllSettings();

            return ApiResponse::success([
                'settings' => $settings,
            ], 'Settings updated successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to update settings: ' . $e->getMessage());

            return ApiResponse::error('Failed to update settings: ' . $e->getMessage(), 'UPDATE_SETTINGS_FAILED', 500);
        }
    }
}
