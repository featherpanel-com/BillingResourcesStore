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

namespace App\Addons\billingresourcesstore\Chat;

use App\App;
use App\Chat\Database;

/**
 * ResourcePackage chat model for CRUD operations on the
 * featherpanel_billingresourcesstore_resource_packages table.
 */
class ResourcePackage
{
    /**
     * @var string the resource packages table name
     */
    private static string $table = 'featherpanel_billingresourcesstore_resource_packages';

    /**
     * Get all enabled packages ordered by sort_order.
     *
     * @return array<array<string,mixed>> Array of packages
     */
    public static function getEnabledPackages(): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE enabled = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all packages (admin use).
     *
     * @return array<array<string,mixed>> Array of packages
     */
    public static function getAll(): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' ORDER BY sort_order ASC, id ASC');
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get package by ID.
     *
     * @param int $id Package ID
     *
     * @return array<string,mixed>|null Package or null if not found
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private static array $allowedCreateFields = [
        'name', 'description', 'memory_limit', 'cpu_limit', 'disk_limit',
        'server_limit', 'database_limit', 'backup_limit', 'allocation_limit',
        'price', 'enabled', 'sort_order',
        'discount_percentage', 'discount_start_date', 'discount_end_date', 'discount_enabled',
    ];

    /**
     * Create a new package.
     *
     * @param array<string,mixed> $data Package data
     *
     * @return int|false Package ID or false on failure
     */
    public static function create(array $data): int | false
    {
        $required = ['name', 'price'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                App::getInstance(true)->getLogger()->error("Missing required field: $field");

                return false;
            }
        }

        $filtered = [];
        foreach (self::$allowedCreateFields as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        if (empty($filtered)) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $fields = array_keys($filtered);
        $placeholders = array_map(fn ($f) => ':' . $f, $fields);
        $sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($filtered)) {
            return (int) $pdo->lastInsertId();
        }

        return false;
    }

    private static array $allowedUpdateFields = [
        'name', 'description', 'memory_limit', 'cpu_limit', 'disk_limit',
        'server_limit', 'database_limit', 'backup_limit', 'allocation_limit',
        'price', 'enabled', 'sort_order',
        'discount_percentage', 'discount_start_date', 'discount_end_date', 'discount_enabled',
    ];

    /**
     * Update package by ID.
     *
     * @param int $id Package ID
     * @param array<string,mixed> $data Fields to update
     *
     * @return bool True on success, false on failure
     */
    public static function updateById(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $filtered = [];
        foreach (self::$allowedUpdateFields as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        if (empty($filtered)) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $fields = array_keys($filtered);
        $setClause = implode(', ', array_map(fn ($f) => $f . ' = :' . $f, $fields));
        $sql = 'UPDATE ' . self::$table . ' SET ' . $setClause . ' WHERE id = :id';
        $filtered['id'] = $id;
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($filtered);
    }

    /**
     * Delete package by ID.
     *
     * @param int $id Package ID
     *
     * @return bool True on success, false on failure
     */
    public static function deleteById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
