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

        $pdo = Database::getPdoConnection();
        $fields = array_keys($data);
        $placeholders = array_map(fn ($f) => ':' . $f, $fields);
        $sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($data)) {
            return (int) $pdo->lastInsertId();
        }

        return false;
    }

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

        if (empty($data)) {
            return false;
        }

        // Prevent updating primary key
        unset($data['id']);

        $pdo = Database::getPdoConnection();
        $fields = array_keys($data);
        $setClause = implode(', ', array_map(fn ($f) => $f . ' = :' . $f, $fields));
        $sql = 'UPDATE ' . self::$table . ' SET ' . $setClause . ' WHERE id = :id';
        $data['id'] = $id;
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($data);
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
