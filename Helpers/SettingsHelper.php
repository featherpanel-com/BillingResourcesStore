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

namespace App\Addons\billingresourcesstore\Helpers;

use App\Plugins\PluginSettings;

/**
 * Helper for managing plugin settings.
 */
class SettingsHelper
{
    /**
     * Get global discount percentage (applied to all packages).
     *
     * @return float Discount percentage (0-100)
     */
    public static function getGlobalDiscount(): float
    {
        $discount = PluginSettings::getSetting('billingresourcesstore', 'global_discount');
        if ($discount === null || $discount === '') {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $discount));
    }

    /**
     * Set global discount percentage.
     *
     * @param float $discount Discount percentage (0-100)
     */
    public static function setGlobalDiscount(float $discount): void
    {
        $discount = max(0.0, min(100.0, $discount));
        PluginSettings::setSetting('billingresourcesstore', 'global_discount', (string) $discount);
    }

    /**
     * Get minimum purchase amount for discounts to apply.
     *
     * @return int Minimum purchase amount in credits
     */
    public static function getMinimumPurchaseForDiscount(): int
    {
        $min = PluginSettings::getSetting('billingresourcesstore', 'minimum_purchase_for_discount');
        if ($min === null || $min === '') {
            return 0;
        }

        return max(0, (int) $min);
    }

    /**
     * Set minimum purchase amount for discounts.
     *
     * @param int $amount Minimum purchase amount in credits
     */
    public static function setMinimumPurchaseForDiscount(int $amount): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'minimum_purchase_for_discount', (string) max(0, $amount));
    }

    /**
     * Get bulk discount thresholds (purchase amount => discount percentage).
     *
     * @return array<int, float> Array of [amount => discount_percentage]
     */
    public static function getBulkDiscounts(): array
    {
        $discountsJson = PluginSettings::getSetting('billingresourcesstore', 'bulk_discounts');
        if ($discountsJson === null || $discountsJson === '') {
            return [];
        }

        $decoded = json_decode($discountsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $amount => $discount) {
            $amount = (int) $amount;
            $discount = max(0.0, min(100.0, (float) $discount));
            if ($amount > 0 && $discount > 0) {
                $result[$amount] = $discount;
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Set bulk discount thresholds.
     *
     * @param array<int, float> $discounts Array of [amount => discount_percentage]
     */
    public static function setBulkDiscounts(array $discounts): void
    {
        $sanitized = [];
        foreach ($discounts as $amount => $discount) {
            $amount = (int) $amount;
            $discount = max(0.0, min(100.0, (float) $discount));
            if ($amount > 0 && $discount > 0) {
                $sanitized[$amount] = $discount;
            }
        }

        ksort($sanitized);
        PluginSettings::setSetting('billingresourcesstore', 'bulk_discounts', json_encode($sanitized));
    }

    /**
     * Get maximum discount percentage that can be applied.
     *
     * @return float Maximum discount percentage (0-100)
     */
    public static function getMaxDiscount(): float
    {
        $max = PluginSettings::getSetting('billingresourcesstore', 'max_discount');
        if ($max === null || $max === '') {
            return 50.0; // Default 50%
        }

        return max(0.0, min(100.0, (float) $max));
    }

    /**
     * Set maximum discount percentage.
     *
     * @param float $max Maximum discount percentage (0-100)
     */
    public static function setMaxDiscount(float $max): void
    {
        $max = max(0.0, min(100.0, $max));
        PluginSettings::setSetting('billingresourcesstore', 'max_discount', (string) $max);
    }

    /**
     * Get whether store is enabled.
     *
     * @return bool True if store is enabled
     */
    public static function isStoreEnabled(): bool
    {
        $enabled = PluginSettings::getSetting('billingresourcesstore', 'store_enabled');

        return $enabled === 'true' || $enabled === null; // Default to enabled
    }

    /**
     * Set whether store is enabled.
     *
     * @param bool $enabled Whether store is enabled
     */
    public static function setStoreEnabled(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'store_enabled', $enabled ? 'true' : 'false');
    }

    /**
     * Get maintenance message (shown when store is disabled).
     *
     * @return string Maintenance message
     */
    public static function getMaintenanceMessage(): string
    {
        $message = PluginSettings::getSetting('billingresourcesstore', 'maintenance_message');
        if ($message === null || $message === '') {
            return 'The resource store is currently under maintenance. Please check back later.';
        }

        return $message;
    }

    /**
     * Set maintenance message.
     *
     * @param string $message Maintenance message
     */
    public static function setMaintenanceMessage(string $message): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'maintenance_message', $message);
    }

    /**
     * Calculate final price with discounts applied.
     *
     * @param int $basePrice Base price in credits
     * @param float|null $packageDiscount Package-specific discount (optional)
     *
     * @return array{final_price: int, discount_applied: float, original_price: int} Price calculation result
     */
    public static function calculatePriceWithDiscounts(int $basePrice, ?float $packageDiscount = null): array
    {
        $originalPrice = $basePrice;
        $totalDiscount = 0.0;

        // Apply package-specific discount if provided
        if ($packageDiscount !== null && $packageDiscount > 0) {
            $totalDiscount = max($totalDiscount, $packageDiscount);
        }

        // Apply global discount
        $globalDiscount = self::getGlobalDiscount();
        if ($globalDiscount > 0) {
            $totalDiscount = max($totalDiscount, $globalDiscount);
        }

        // Apply bulk discount based on purchase amount
        $bulkDiscounts = self::getBulkDiscounts();
        foreach ($bulkDiscounts as $threshold => $discount) {
            if ($basePrice >= $threshold) {
                $totalDiscount = max($totalDiscount, $discount);
            }
        }

        // Cap discount at maximum
        $maxDiscount = self::getMaxDiscount();
        $totalDiscount = min($totalDiscount, $maxDiscount);

        $finalPrice = (int) round($basePrice * (1 - ($totalDiscount / 100)));

        return [
            'final_price' => max(0, $finalPrice),
            'discount_applied' => $totalDiscount,
            'original_price' => $originalPrice,
        ];
    }

    /**
     * Get whether individual resource purchases are enabled.
     *
     * @return bool True if individual purchases are enabled
     */
    public static function isIndividualPurchasesEnabled(): bool
    {
        $enabled = PluginSettings::getSetting('billingresourcesstore', 'individual_purchases_enabled');

        return $enabled === 'true';
    }

    /**
     * Set whether individual resource purchases are enabled.
     *
     * @param bool $enabled Whether individual purchases are enabled
     */
    public static function setIndividualPurchasesEnabled(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'individual_purchases_enabled', $enabled ? 'true' : 'false');
    }

    /**
     * Get resource prices (per unit).
     *
     * @return array<string, int> Array of [resource_type => price_per_unit]
     */
    public static function getResourcePrices(): array
    {
        $pricesJson = PluginSettings::getSetting('billingresourcesstore', 'resource_prices');
        if ($pricesJson === null || $pricesJson === '') {
            return [];
        }

        $decoded = json_decode($pricesJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        $allowedTypes = [
            'memory_limit',
            'cpu_limit',
            'disk_limit',
            'server_limit',
            'database_limit',
            'backup_limit',
            'allocation_limit',
        ];

        foreach ($allowedTypes as $type) {
            if (isset($decoded[$type])) {
                $result[$type] = max(0, (int) $decoded[$type]);
            }
        }

        return $result;
    }

    /**
     * Set resource prices.
     *
     * @param array<string, int> $prices Array of [resource_type => price_per_unit]
     */
    public static function setResourcePrices(array $prices): void
    {
        $sanitized = [];
        $allowedTypes = [
            'memory_limit',
            'cpu_limit',
            'disk_limit',
            'server_limit',
            'database_limit',
            'backup_limit',
            'allocation_limit',
        ];

        foreach ($allowedTypes as $type) {
            if (isset($prices[$type])) {
                $sanitized[$type] = max(0, (int) $prices[$type]);
            }
        }

        PluginSettings::setSetting('billingresourcesstore', 'resource_prices', json_encode($sanitized));
    }

    /**
     * Get minimum purchase amount for individual resources.
     *
     * @param string $resourceType Resource type
     *
     * @return int Minimum purchase amount
     */
    public static function getMinimumResourcePurchase(string $resourceType): int
    {
        $minJson = PluginSettings::getSetting('billingresourcesstore', 'minimum_resource_purchases');
        if ($minJson === null || $minJson === '') {
            // Default minimums
            $defaults = [
                'memory_limit' => 128, // 128 MB
                'cpu_limit' => 1, // 1%
                'disk_limit' => 128, // 128 MB
                'server_limit' => 1,
                'database_limit' => 1,
                'backup_limit' => 1,
                'allocation_limit' => 1,
            ];

            return $defaults[$resourceType] ?? 1;
        }

        $decoded = json_decode($minJson, true);
        if (!is_array($decoded) || !isset($decoded[$resourceType])) {
            return 1;
        }

        return max(1, (int) $decoded[$resourceType]);
    }

    /**
     * Set minimum purchase amounts for resources.
     *
     * @param array<string, int> $minimums Array of [resource_type => minimum_amount]
     */
    public static function setMinimumResourcePurchases(array $minimums): void
    {
        $sanitized = [];
        $allowedTypes = [
            'memory_limit',
            'cpu_limit',
            'disk_limit',
            'server_limit',
            'database_limit',
            'backup_limit',
            'allocation_limit',
        ];

        foreach ($allowedTypes as $type) {
            if (isset($minimums[$type])) {
                $sanitized[$type] = max(1, (int) $minimums[$type]);
            }
        }

        PluginSettings::setSetting('billingresourcesstore', 'minimum_resource_purchases', json_encode($sanitized));
    }

    /**
     * Get maximum purchase amount for individual resources.
     *
     * @param string $resourceType Resource type
     *
     * @return int|null Maximum purchase amount (null for unlimited)
     */
    public static function getMaximumResourcePurchase(string $resourceType): ?int
    {
        $maxJson = PluginSettings::getSetting('billingresourcesstore', 'maximum_resource_purchases');
        if ($maxJson === null || $maxJson === '') {
            return null; // Unlimited by default
        }

        $decoded = json_decode($maxJson, true);
        if (!is_array($decoded) || !isset($decoded[$resourceType])) {
            return null; // Unlimited
        }

        $max = (int) $decoded[$resourceType];

        return $max > 0 ? $max : null;
    }

    /**
     * Set maximum purchase amounts for resources.
     *
     * @param array<string, int|null> $maximums Array of [resource_type => maximum_amount] (null for unlimited)
     */
    public static function setMaximumResourcePurchases(array $maximums): void
    {
        $sanitized = [];
        $allowedTypes = [
            'memory_limit',
            'cpu_limit',
            'disk_limit',
            'server_limit',
            'database_limit',
            'backup_limit',
            'allocation_limit',
        ];

        foreach ($allowedTypes as $type) {
            if (isset($maximums[$type])) {
                $value = $maximums[$type];
                if ($value === null || $value === '' || $value === 0) {
                    // Skip unlimited values
                    continue;
                }
                $sanitized[$type] = max(1, (int) $value);
            }
        }

        PluginSettings::setSetting('billingresourcesstore', 'maximum_resource_purchases', json_encode($sanitized));
    }

    /**
     * Get default front page display mode.
     *
     * @return string 'packages' or 'individual'
     */
    public static function getFrontPageDisplay(): string
    {
        $display = PluginSettings::getSetting('billingresourcesstore', 'front_page_display');
        if ($display === null || $display === '') {
            return 'packages'; // Default to packages
        }

        return in_array($display, ['packages', 'individual'], true) ? $display : 'packages';
    }

    /**
     * Set default front page display mode.
     *
     * @param string $display 'packages' or 'individual'
     */
    public static function setFrontPageDisplay(string $display): void
    {
        if (!in_array($display, ['packages', 'individual'], true)) {
            $display = 'packages';
        }
        PluginSettings::setSetting('billingresourcesstore', 'front_page_display', $display);
    }

    /**
     * Check if invoice generation is enabled globally.
     *
     * @return bool True if invoice generation is enabled
     */
    public static function isInvoiceGenerationEnabled(): bool
    {
        $enabled = PluginSettings::getSetting('billingresourcesstore', 'invoice_generation_enabled');
        if ($enabled === null || $enabled === '') {
            return false; // Default to disabled
        }

        return $enabled === 'true';
    }

    /**
     * Set invoice generation enabled state.
     *
     * @param bool $enabled Whether invoice generation is enabled
     */
    public static function setInvoiceGenerationEnabled(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'invoice_generation_enabled', $enabled ? 'true' : 'false');
    }

    /**
     * Check if invoices should be generated for packages.
     *
     * @return bool True if invoices should be generated for packages
     */
    public static function shouldGenerateInvoiceForPackages(): bool
    {
        if (!self::isInvoiceGenerationEnabled()) {
            return false;
        }

        $enabled = PluginSettings::getSetting('billingresourcesstore', 'invoice_generation_packages');
        if ($enabled === null || $enabled === '') {
            return false; // Default to disabled
        }

        return $enabled === 'true';
    }

    /**
     * Set whether invoices should be generated for packages.
     *
     * @param bool $enabled Whether to generate invoices for packages
     */
    public static function setInvoiceGenerationForPackages(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'invoice_generation_packages', $enabled ? 'true' : 'false');
    }

    /**
     * Check if invoices should be generated for individual resources.
     *
     * @return bool True if invoices should be generated for individual resources
     */
    public static function shouldGenerateInvoiceForIndividual(): bool
    {
        if (!self::isInvoiceGenerationEnabled()) {
            return false;
        }

        $enabled = PluginSettings::getSetting('billingresourcesstore', 'invoice_generation_individual');
        if ($enabled === null || $enabled === '') {
            return false; // Default to disabled
        }

        return $enabled === 'true';
    }

    /**
     * Set whether invoices should be generated for individual resources.
     *
     * @param bool $enabled Whether to generate invoices for individual resources
     */
    public static function setInvoiceGenerationForIndividual(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesstore', 'invoice_generation_individual', $enabled ? 'true' : 'false');
    }

    /**
     * Get all settings.
     *
     * @return array<string,mixed> Settings structure
     */
    public static function getAllSettings(): array
    {
        return [
            'store_enabled' => self::isStoreEnabled(),
            'maintenance_message' => self::getMaintenanceMessage(),
            'individual_purchases_enabled' => self::isIndividualPurchasesEnabled(),
            'global_discount' => self::getGlobalDiscount(),
            'minimum_purchase_for_discount' => self::getMinimumPurchaseForDiscount(),
            'bulk_discounts' => self::getBulkDiscounts(),
            'max_discount' => self::getMaxDiscount(),
            'front_page_display' => self::getFrontPageDisplay(),
            'invoice_generation_enabled' => self::isInvoiceGenerationEnabled(),
            'invoice_generation_packages' => self::shouldGenerateInvoiceForPackages(),
            'invoice_generation_individual' => self::shouldGenerateInvoiceForIndividual(),
        ];
    }
}
