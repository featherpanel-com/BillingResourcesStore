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

namespace App\Addons\billingresourcesstore\Chat;

use App\Chat\Database;

/**
 * Individual Resource Model.
 */
class IndividualResource
{
    protected static string $table = 'featherpanel_billingresourcesstore_individual_resources';

    /**
     * Get all individual resources.
     *
     * @param bool $enabledOnly Only return enabled resources
     *
     * @return array<array<string,mixed>> Array of resources
     */
    public static function getAll(bool $enabledOnly = false): array
    {
        $query = Database::getPdoConnection()->prepare('SELECT * FROM `' . self::$table . '`' . ($enabledOnly ? ' WHERE `enabled` = 1' : '') . ' ORDER BY `sort_order` ASC, `name` ASC');
        $query->execute();

        $results = $query->fetchAll(\PDO::FETCH_ASSOC);
        if ($results === false) {
            return [];
        }

        return $results;
    }

    /**
     * Get resource by ID.
     *
     * @param int $id Resource ID
     *
     * @return array<string,mixed>|null Resource data or null if not found
     */
    public static function getById(int $id): ?array
    {
        $query = Database::getPdoConnection()->prepare('SELECT * FROM `' . self::$table . '` WHERE `id` = :id LIMIT 1');
        $query->execute(['id' => $id]);

        $result = $query->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Create a new individual resource.
     *
     * @param array<string,mixed> $data Resource data
     *
     * @return int|false Created resource ID or false on failure
     */
    public static function create(array $data): int | false
    {
        $allowedFields = [
            'name',
            'description',
            'resource_type',
            'unit',
            'price_per_unit',
            'minimum_amount',
            'maximum_amount',
            'discount_percentage',
            'discount_start_date',
            'discount_end_date',
            'discount_enabled',
            'enabled',
            'sort_order',
        ];

        $fields = [];
        $values = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "`{$field}`";
                $values[] = ":{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'INSERT INTO `' . self::$table . '` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        $pdo = Database::getPdoConnection();
        $query = $pdo->prepare($sql);
        $success = $query->execute($params);

        if (!$success) {
            return false;
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update resource by ID.
     *
     * @param int $id Resource ID
     * @param array<string,mixed> $data Resource data to update
     *
     * @return bool True on success, false on failure
     */
    public static function updateById(int $id, array $data): bool
    {
        $allowedFields = [
            'name',
            'description',
            'resource_type',
            'unit',
            'price_per_unit',
            'minimum_amount',
            'maximum_amount',
            'discount_percentage',
            'discount_start_date',
            'discount_end_date',
            'discount_enabled',
            'enabled',
            'sort_order',
        ];

        $updates = [];
        $params = ['id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "`{$field}` = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE `' . self::$table . '` SET ' . implode(', ', $updates) . ' WHERE `id` = :id';
        $query = Database::getPdoConnection()->prepare($sql);

        return $query->execute($params);
    }

    /**
     * Delete resource by ID.
     *
     * @param int $id Resource ID
     *
     * @return bool True on success, false on failure
     */
    public static function deleteById(int $id): bool
    {
        $query = Database::getPdoConnection()->prepare('DELETE FROM `' . self::$table . '` WHERE `id` = :id');
        $query->execute(['id' => $id]);

        return $query->rowCount() > 0;
    }

    /**
     * Calculate final price with discount applied.
     *
     * @param array<string,mixed> $resource Resource data
     * @param int $amount Amount to purchase
     *
     * @return array{final_price: int, discount_applied: float, original_price: int} Price calculation result
     */
    public static function calculatePriceWithDiscount(array $resource, int $amount): array
    {
        $pricePerUnit = (int) ($resource['price_per_unit'] ?? 0);
        $originalPrice = $pricePerUnit * $amount;
        $discountApplied = 0.0;

        // Check if discount is enabled and valid
        $discountEnabled = (bool) ($resource['discount_enabled'] ?? false);
        if ($discountEnabled) {
            $discountPercentage = (float) ($resource['discount_percentage'] ?? 0.0);
            $discountStartDate = $resource['discount_start_date'] ?? null;
            $discountEndDate = $resource['discount_end_date'] ?? null;

            $now = time();
            $startValid = $discountStartDate === null || strtotime($discountStartDate) <= $now;
            $endValid = $discountEndDate === null || strtotime($discountEndDate) >= $now;

            if ($startValid && $endValid && $discountPercentage > 0) {
                $discountApplied = $discountPercentage;
            }
        }

        $finalPrice = $originalPrice;
        if ($discountApplied > 0) {
            $discountAmount = ($originalPrice * $discountApplied) / 100;
            $finalPrice = max(0, (int) ($originalPrice - $discountAmount));
        }

        return [
            'final_price' => $finalPrice,
            'discount_applied' => $discountApplied,
            'original_price' => $originalPrice,
        ];
    }
}
