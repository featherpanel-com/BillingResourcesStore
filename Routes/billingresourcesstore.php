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

use App\App;
use App\Permissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingresourcesstore\Controllers\User\StoreController as UserStoreController;
use App\Addons\billingresourcesstore\Controllers\Admin\StoreController as AdminStoreController;
use App\Addons\billingresourcesstore\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Addons\billingresourcesstore\Controllers\User\IndividualResourcesController as UserIndividualResourcesController;
use App\Addons\billingresourcesstore\Controllers\Admin\IndividualResourcesController as AdminIndividualResourcesController;

return function (RouteCollection $routes): void {
    // User Routes (require authentication)
    // Get available packages
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesstore-user-packages',
        '/api/user/billingresourcesstore/packages',
        function (Request $request) {
            return (new UserStoreController())->getPackages($request);
        },
        ['GET']
    );

    // Purchase a package
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesstore-user-purchase',
        '/api/user/billingresourcesstore/purchase',
        function (Request $request) {
            return (new UserStoreController())->purchasePackage($request);
        },
        ['POST']
    );

    // Get purchase history
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesstore-user-purchases',
        '/api/user/billingresourcesstore/purchases',
        function (Request $request) {
            return (new UserStoreController())->getPurchases($request);
        },
        ['GET']
    );

    // Individual Resource Routes
    // Get individual resource prices
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesstore-user-individual-resources',
        '/api/user/billingresourcesstore/individual-resources',
        function (Request $request) {
            return (new UserIndividualResourcesController())->getResourcePrices($request);
        },
        ['GET']
    );

    // Purchase individual resources
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesstore-user-individual-resources-purchase',
        '/api/user/billingresourcesstore/individual-resources/purchase',
        function (Request $request) {
            return (new UserIndividualResourcesController())->purchaseResources($request);
        },
        ['POST']
    );

    // Admin Routes (require admin authentication)
    // Get all packages
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-packages',
        '/api/admin/billingresourcesstore/packages',
        function (Request $request) {
            return (new AdminStoreController())->getPackages($request);
        },
        Permissions::ADMIN_SETTINGS_VIEW,
        ['GET']
    );

    // Create package
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-packages-create',
        '/api/admin/billingresourcesstore/packages',
        function (Request $request) {
            return (new AdminStoreController())->createPackage($request);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['POST']
    );

    // Update package
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-packages-update',
        '/api/admin/billingresourcesstore/packages/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            return (new AdminStoreController())->updatePackage($request, (int) $id);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['PUT']
    );

    // Delete package
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-packages-delete',
        '/api/admin/billingresourcesstore/packages/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            return (new AdminStoreController())->deletePackage($request, (int) $id);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['DELETE']
    );

    // Settings Routes
    // Get settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-settings',
        '/api/admin/billingresourcesstore/settings',
        function (Request $request) {
            return (new AdminSettingsController())->getSettings($request);
        },
        Permissions::ADMIN_SETTINGS_VIEW,
        ['GET']
    );

    // Update settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-settings-update',
        '/api/admin/billingresourcesstore/settings',
        function (Request $request) {
            return (new AdminSettingsController())->updateSettings($request);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['PUT']
    );

    // Individual Resources Admin Routes
    // Get all individual resources
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-individual-resources',
        '/api/admin/billingresourcesstore/individual-resources',
        function (Request $request) {
            return (new AdminIndividualResourcesController())->getResources($request);
        },
        Permissions::ADMIN_SETTINGS_VIEW,
        ['GET']
    );

    // Create individual resource
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-individual-resources-create',
        '/api/admin/billingresourcesstore/individual-resources',
        function (Request $request) {
            return (new AdminIndividualResourcesController())->createResource($request);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['POST']
    );

    // Update individual resource
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-individual-resources-update',
        '/api/admin/billingresourcesstore/individual-resources/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            return (new AdminIndividualResourcesController())->updateResource($request, (int) $id);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['PUT']
    );

    // Delete individual resource
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesstore-admin-individual-resources-delete',
        '/api/admin/billingresourcesstore/individual-resources/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return \App\Helpers\ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            return (new AdminIndividualResourcesController())->deleteResource($request, (int) $id);
        },
        Permissions::ADMIN_SETTINGS_EDIT,
        ['DELETE']
    );
};
