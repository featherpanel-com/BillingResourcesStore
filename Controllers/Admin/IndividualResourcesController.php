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
use App\Addons\billingresourcesstore\Chat\IndividualResource;

#[OA\Tag(name: 'Admin - Billing Resources Store Individual', description: 'Admin individual resource management endpoints')]
class IndividualResourcesController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesstore/individual-resources',
        summary: 'Get all individual resources',
        description: 'Get all individual resources (admin)',
        tags: ['Admin - Billing Resources Store Individual'],
        responses: [
            new OA\Response(response: 200, description: 'Resources retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getResources(Request $request): Response
    {
        try {
            $resources = IndividualResource::getAll(false);

            return ApiResponse::success([
                'resources' => $resources,
            ], 'Resources retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get resources: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve resources: ' . $e->getMessage(), 'GET_RESOURCES_FAILED', 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesstore/individual-resources',
        summary: 'Create individual resource',
        description: 'Create a new individual resource',
        tags: ['Admin - Billing Resources Store Individual'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'resource_type', type: 'string'),
                    new OA\Property(property: 'unit', type: 'string'),
                    new OA\Property(property: 'price_per_unit', type: 'integer'),
                    new OA\Property(property: 'minimum_amount', type: 'integer'),
                    new OA\Property(property: 'maximum_amount', type: 'integer'),
                    new OA\Property(property: 'discount_percentage', type: 'number'),
                    new OA\Property(property: 'discount_start_date', type: 'string'),
                    new OA\Property(property: 'discount_end_date', type: 'string'),
                    new OA\Property(property: 'discount_enabled', type: 'boolean'),
                    new OA\Property(property: 'enabled', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ],
                required: ['name', 'resource_type', 'unit', 'price_per_unit']
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Resource created successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function createResource(Request $request): Response
    {
        $payload = json_decode($request->getContent() ?: '[]', true, 32);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        $name = trim($payload['name'] ?? '');
        $resourceType = trim($payload['resource_type'] ?? '');
        $unit = trim($payload['unit'] ?? 'MB');
        $pricePerUnit = isset($payload['price_per_unit']) ? max(0, (int) $payload['price_per_unit']) : 0;

        if (empty($name)) {
            return ApiResponse::error('Name is required', 'NAME_REQUIRED', 400);
        }

        if (empty($resourceType)) {
            return ApiResponse::error('Resource type is required', 'RESOURCE_TYPE_REQUIRED', 400);
        }

        if ($pricePerUnit <= 0) {
            return ApiResponse::error('Price per unit must be greater than 0', 'INVALID_PRICE', 400);
        }

        $allowedTypes = [
            'memory_limit',
            'cpu_limit',
            'disk_limit',
            'server_limit',
            'database_limit',
            'backup_limit',
            'allocation_limit',
        ];

        if (!in_array($resourceType, $allowedTypes, true)) {
            return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
        }

        try {
            $data = [
                'name' => $name,
                'description' => $payload['description'] ?? null,
                'resource_type' => $resourceType,
                'unit' => $unit,
                'price_per_unit' => $pricePerUnit,
                'minimum_amount' => max(1, (int) ($payload['minimum_amount'] ?? 1)),
                'maximum_amount' => isset($payload['maximum_amount']) && $payload['maximum_amount'] > 0 ? (int) $payload['maximum_amount'] : null,
                'discount_percentage' => max(0.0, min(100.0, (float) ($payload['discount_percentage'] ?? 0.0))),
                'discount_start_date' => !empty($payload['discount_start_date']) ? $payload['discount_start_date'] : null,
                'discount_end_date' => !empty($payload['discount_end_date']) ? $payload['discount_end_date'] : null,
                'discount_enabled' => (bool) ($payload['discount_enabled'] ?? false) ? 1 : 0,
                'enabled' => (bool) ($payload['enabled'] ?? true) ? 1 : 0,
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
            ];

            $id = IndividualResource::create($data);
            if ($id === false) {
                return ApiResponse::error('Failed to create resource', 'CREATE_FAILED', 500);
            }

            $resource = IndividualResource::getById($id);
            if ($resource === null) {
                return ApiResponse::error('Resource created but could not be retrieved', 'RETRIEVE_FAILED', 500);
            }

            return ApiResponse::success([
                'resource' => $resource,
            ], 'Resource created successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to create resource: ' . $e->getMessage());

            return ApiResponse::error('Failed to create resource: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    #[OA\Put(
        path: '/api/admin/billingresourcesstore/individual-resources/{id}',
        summary: 'Update individual resource',
        description: 'Update an existing individual resource',
        tags: ['Admin - Billing Resources Store Individual'],
        responses: [
            new OA\Response(response: 200, description: 'Resource updated successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Resource not found'),
        ]
    )]
    public function updateResource(Request $request, int $id): Response
    {
        $resource = IndividualResource::getById($id);
        if ($resource === null) {
            return ApiResponse::error('Resource not found', 'RESOURCE_NOT_FOUND', 404);
        }

        $payload = json_decode($request->getContent() ?: '[]', true, 32);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        try {
            $data = [];

            if (isset($payload['name'])) {
                $name = trim($payload['name']);
                if (empty($name)) {
                    return ApiResponse::error('Name cannot be empty', 'INVALID_NAME', 400);
                }
                $data['name'] = $name;
            }

            if (isset($payload['description'])) {
                $data['description'] = $payload['description'];
            }

            if (isset($payload['resource_type'])) {
                $resourceType = trim($payload['resource_type']);
                $allowedTypes = [
                    'memory_limit',
                    'cpu_limit',
                    'disk_limit',
                    'server_limit',
                    'database_limit',
                    'backup_limit',
                    'allocation_limit',
                ];
                if (!in_array($resourceType, $allowedTypes, true)) {
                    return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
                }
                $data['resource_type'] = $resourceType;
            }

            if (isset($payload['unit'])) {
                $data['unit'] = trim($payload['unit']);
            }

            if (isset($payload['price_per_unit'])) {
                $pricePerUnit = max(0, (int) $payload['price_per_unit']);
                if ($pricePerUnit <= 0) {
                    return ApiResponse::error('Price per unit must be greater than 0', 'INVALID_PRICE', 400);
                }
                $data['price_per_unit'] = $pricePerUnit;
            }

            if (isset($payload['minimum_amount'])) {
                $data['minimum_amount'] = max(1, (int) $payload['minimum_amount']);
            }

            if (isset($payload['maximum_amount'])) {
                $data['maximum_amount'] = isset($payload['maximum_amount']) && $payload['maximum_amount'] > 0 ? (int) $payload['maximum_amount'] : null;
            }

            if (isset($payload['discount_percentage'])) {
                $data['discount_percentage'] = max(0.0, min(100.0, (float) $payload['discount_percentage']));
            }

            if (isset($payload['discount_start_date'])) {
                $data['discount_start_date'] = !empty($payload['discount_start_date']) ? $payload['discount_start_date'] : null;
            }

            if (isset($payload['discount_end_date'])) {
                $data['discount_end_date'] = !empty($payload['discount_end_date']) ? $payload['discount_end_date'] : null;
            }

            if (isset($payload['discount_enabled'])) {
                $data['discount_enabled'] = (bool) $payload['discount_enabled'] ? 1 : 0;
            }

            if (isset($payload['enabled'])) {
                $data['enabled'] = (bool) $payload['enabled'] ? 1 : 0;
            }

            if (isset($payload['sort_order'])) {
                $data['sort_order'] = (int) $payload['sort_order'];
            }

            if (empty($data)) {
                return ApiResponse::error('No fields to update', 'NO_FIELDS', 400);
            }

            if (!IndividualResource::updateById($id, $data)) {
                return ApiResponse::error('Failed to update resource', 'UPDATE_FAILED', 500);
            }

            $updatedResource = IndividualResource::getById($id);
            if ($updatedResource === null) {
                return ApiResponse::error('Resource updated but could not be retrieved', 'RETRIEVE_FAILED', 500);
            }

            return ApiResponse::success([
                'resource' => $updatedResource,
            ], 'Resource updated successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to update resource: ' . $e->getMessage());

            return ApiResponse::error('Failed to update resource: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesstore/individual-resources/{id}',
        summary: 'Delete individual resource',
        description: 'Delete an individual resource',
        tags: ['Admin - Billing Resources Store Individual'],
        responses: [
            new OA\Response(response: 200, description: 'Resource deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Resource not found'),
        ]
    )]
    public function deleteResource(Request $request, int $id): Response
    {
        $resource = IndividualResource::getById($id);
        if ($resource === null) {
            return ApiResponse::error('Resource not found', 'RESOURCE_NOT_FOUND', 404);
        }

        try {
            if (!IndividualResource::deleteById($id)) {
                return ApiResponse::error('Failed to delete resource', 'DELETE_FAILED', 500);
            }

            return ApiResponse::success([], 'Resource deleted successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete resource: ' . $e->getMessage());

            return ApiResponse::error('Failed to delete resource: ' . $e->getMessage(), 'DELETE_FAILED', 500);
        }
    }
}
