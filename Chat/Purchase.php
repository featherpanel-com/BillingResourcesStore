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
 * Purchase chat model for CRUD operations on the
 * featherpanel_billingresourcesstore_purchases table.
 */
class Purchase
{
    /**
     * @var string the purchases table name
     */
    private static string $table = 'featherpanel_billingresourcesstore_purchases';

    private static array $allowedCreateFields = [
        'user_id', 'package_id', 'price',
        'memory_limit', 'cpu_limit', 'disk_limit', 'server_limit',
        'database_limit', 'backup_limit', 'allocation_limit',
    ];

    /**
     * Create a new purchase record.
     *
     * @param array<string,mixed> $data Purchase data
     *
     * @return int|false Purchase ID or false on failure
     */
    public static function create(array $data): int | false
    {
        $required = ['user_id', 'package_id', 'price'];
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

    /**
     * Get purchases by user ID.
     *
     * @param int $userId User ID
     * @param int $page Page number (1-based)
     * @param int $limit Results per page
     *
     * @return array<array<string,mixed>> Array of purchases
     */
    public static function getByUserId(int $userId, int $page = 1, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            'SELECT p.*, rp.name as package_name FROM ' . self::$table . ' p ' .
            'LEFT JOIN featherpanel_billingresourcesstore_resource_packages rp ON p.package_id = rp.id ' .
            'WHERE p.user_id = :user_id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get purchase count by user ID.
     *
     * @param int $userId User ID
     *
     * @return int Count of purchases
     */
    public static function countByUserId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . self::$table . ' WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
