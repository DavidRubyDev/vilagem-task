<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use PDO;
use RuntimeException;

/** Reads a SQLite file in a test, failing loudly when a query does not run. */
final class SqliteFile
{
    public static function scalar(string $file, string $sql): int|string|null
    {
        /** @var int|string|false|null $value */
        $value = self::statement($file, $sql)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    public static function row(string $file, string $sql): ?array
    {
        /** @var array<string, int|string|null>|false $row */
        $row = self::statement($file, $sql)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<list<int|string|null>>
     */
    public static function rows(string $file, string $sql): array
    {
        /** @var list<list<int|string|null>> $rows */
        $rows = self::statement($file, $sql)->fetchAll(PDO::FETCH_NUM);

        return $rows;
    }

    private static function statement(string $file, string $sql): \PDOStatement
    {
        $statement = new PDO('sqlite:' . $file)->query($sql);
        if ($statement === false) {
            throw new RuntimeException('Cannot run ' . $sql . ' on ' . $file);
        }

        return $statement;
    }
}
