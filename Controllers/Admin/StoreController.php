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

namespace App\Addons\billingresourcesstore\Controllers\Admin;

use App\App;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingresourcesstore\Chat\ResourcePackage;

#[OA\Tag(name: 'Admin - Billing Resources Store', description: 'Admin resource store management endpoints')]
class StoreController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesstore/packages',
        summary: 'Get all resource packages',
        description: 'Get all resource packages (admin)',
        tags: ['Admin - Billing Resources Store'],
        responses: [
            new OA\Response(response: 200, description: 'Packages retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getPackages(Request $request): Response
    {
        try {
            $packages = ResourcePackage::getAll();

            return ApiResponse::success([
                'packages' => array_values($packages),
            ], 'Packages retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get packages: ' . $e->getMessage());

            return ApiResponse::error('Failed to retrieve packages: ' . $e->getMessage(), 'GET_PACKAGES_FAILED', 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesstore/packages',
        summary: 'Create a resource package',
        description: 'Create a new resource package',
        tags: ['Admin - Billing Resources Store'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'memory_limit', type: 'integer'),
                    new OA\Property(property: 'cpu_limit', type: 'integer'),
                    new OA\Property(property: 'disk_limit', type: 'integer'),
                    new OA\Property(property: 'server_limit', type: 'integer'),
                    new OA\Property(property: 'database_limit', type: 'integer'),
                    new OA\Property(property: 'backup_limit', type: 'integer'),
                    new OA\Property(property: 'allocation_limit', type: 'integer'),
                    new OA\Property(property: 'price', type: 'integer'),
                    new OA\Property(property: 'enabled', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ],
                required: ['name', 'price']
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Package created successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function createPackage(Request $request): Response
    {
        $payload = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        $data = [
            'name' => trim($payload['name'] ?? ''),
            'description' => trim($payload['description'] ?? ''),
            'memory_limit' => max(0, (int) ($payload['memory_limit'] ?? 0)),
            'cpu_limit' => max(0, (int) ($payload['cpu_limit'] ?? 0)),
            'disk_limit' => max(0, (int) ($payload['disk_limit'] ?? 0)),
            'server_limit' => max(0, (int) ($payload['server_limit'] ?? 0)),
            'database_limit' => max(0, (int) ($payload['database_limit'] ?? 0)),
            'backup_limit' => max(0, (int) ($payload['backup_limit'] ?? 0)),
            'allocation_limit' => max(0, (int) ($payload['allocation_limit'] ?? 0)),
            'price' => max(0, (int) ($payload['price'] ?? 0)),
            'enabled' => isset($payload['enabled']) ? ((bool) $payload['enabled'] ? 1 : 0) : 1,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'discount_percentage' => max(0.0, min(100.0, (float) ($payload['discount_percentage'] ?? 0))),
            'discount_start_date' => !empty($payload['discount_start_date']) ? $payload['discount_start_date'] : null,
            'discount_end_date' => !empty($payload['discount_end_date']) ? $payload['discount_end_date'] : null,
            'discount_enabled' => isset($payload['discount_enabled']) ? ((bool) $payload['discount_enabled'] ? 1 : 0) : 0,
        ];

        if ($data['name'] === '') {
            return ApiResponse::error('Package name is required', 'MISSING_NAME', 400);
        }

        if ($data['price'] <= 0) {
            return ApiResponse::error('Package price must be greater than 0', 'INVALID_PRICE', 400);
        }

        try {
            $packageId = ResourcePackage::create($data);
            if ($packageId === false) {
                return ApiResponse::error('Failed to create package', 'CREATE_FAILED', 500);
            }

            $package = ResourcePackage::getById($packageId);

            return ApiResponse::success([
                'package' => $package,
            ], 'Package created successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to create package: ' . $e->getMessage());

            return ApiResponse::error('Failed to create package: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    #[OA\Put(
        path: '/api/admin/billingresourcesstore/packages/{id}',
        summary: 'Update a resource package',
        description: 'Update an existing resource package',
        tags: ['Admin - Billing Resources Store'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'memory_limit', type: 'integer'),
                    new OA\Property(property: 'cpu_limit', type: 'integer'),
                    new OA\Property(property: 'disk_limit', type: 'integer'),
                    new OA\Property(property: 'server_limit', type: 'integer'),
                    new OA\Property(property: 'database_limit', type: 'integer'),
                    new OA\Property(property: 'backup_limit', type: 'integer'),
                    new OA\Property(property: 'allocation_limit', type: 'integer'),
                    new OA\Property(property: 'price', type: 'integer'),
                    new OA\Property(property: 'enabled', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Package updated successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Package not found'),
        ]
    )]
    public function updatePackage(Request $request, int $id): Response
    {
        $package = ResourcePackage::getById($id);
        if (!$package) {
            return ApiResponse::error('Package not found', 'PACKAGE_NOT_FOUND', 404);
        }

        $payload = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($payload)) {
            return ApiResponse::error('Invalid JSON payload provided.', 'INVALID_JSON_PAYLOAD', 400);
        }

        $data = [];
        if (isset($payload['name'])) {
            $data['name'] = trim($payload['name']);
            if ($data['name'] === '') {
                return ApiResponse::error('Package name cannot be empty', 'INVALID_NAME', 400);
            }
        }
        if (isset($payload['description'])) {
            $data['description'] = trim($payload['description']);
        }
        if (isset($payload['memory_limit'])) {
            $data['memory_limit'] = max(0, (int) $payload['memory_limit']);
        }
        if (isset($payload['cpu_limit'])) {
            $data['cpu_limit'] = max(0, (int) $payload['cpu_limit']);
        }
        if (isset($payload['disk_limit'])) {
            $data['disk_limit'] = max(0, (int) $payload['disk_limit']);
        }
        if (isset($payload['server_limit'])) {
            $data['server_limit'] = max(0, (int) $payload['server_limit']);
        }
        if (isset($payload['database_limit'])) {
            $data['database_limit'] = max(0, (int) $payload['database_limit']);
        }
        if (isset($payload['backup_limit'])) {
            $data['backup_limit'] = max(0, (int) $payload['backup_limit']);
        }
        if (isset($payload['allocation_limit'])) {
            $data['allocation_limit'] = max(0, (int) $payload['allocation_limit']);
        }
        if (isset($payload['price'])) {
            $data['price'] = max(0, (int) $payload['price']);
            if ($data['price'] <= 0) {
                return ApiResponse::error('Package price must be greater than 0', 'INVALID_PRICE', 400);
            }
        }
        if (isset($payload['enabled'])) {
            $data['enabled'] = (bool) $payload['enabled'] ? 1 : 0;
        }
        if (isset($payload['sort_order'])) {
            $data['sort_order'] = (int) $payload['sort_order'];
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

        if (empty($data)) {
            return ApiResponse::error('No fields to update', 'NO_FIELDS', 400);
        }

        try {
            if (!ResourcePackage::updateById($id, $data)) {
                return ApiResponse::error('Failed to update package', 'UPDATE_FAILED', 500);
            }

            $updatedPackage = ResourcePackage::getById($id);

            return ApiResponse::success([
                'package' => $updatedPackage,
            ], 'Package updated successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to update package: ' . $e->getMessage());

            return ApiResponse::error('Failed to update package: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesstore/packages/{id}',
        summary: 'Delete a resource package',
        description: 'Delete an existing resource package',
        tags: ['Admin - Billing Resources Store'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Package deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Package not found'),
        ]
    )]
    public function deletePackage(Request $request, int $id): Response
    {
        $package = ResourcePackage::getById($id);
        if (!$package) {
            return ApiResponse::error('Package not found', 'PACKAGE_NOT_FOUND', 404);
        }

        try {
            if (!ResourcePackage::deleteById($id)) {
                return ApiResponse::error('Failed to delete package', 'DELETE_FAILED', 500);
            }

            return ApiResponse::success([], 'Package deleted successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete package: ' . $e->getMessage());

            return ApiResponse::error('Failed to delete package: ' . $e->getMessage(), 'DELETE_FAILED', 500);
        }
    }
}
