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
 * Purchase chat model for CRUD operations on the
 * featherpanel_billingresourcesstore_purchases table.
 */
class Purchase
{
    /**
     * @var string the purchases table name
     */
    private static string $table = 'featherpanel_billingresourcesstore_purchases';

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
