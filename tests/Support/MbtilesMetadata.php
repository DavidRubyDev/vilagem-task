<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use PDO;
use RuntimeException;

final class MbtilesMetadata
{
    /**
     * @return array<string, string>
     */
    public static function read(string $file): array
    {
        $statement = new PDO('sqlite:' . $file)->query('SELECT name, value FROM metadata');
        if ($statement === false) {
            throw new RuntimeException('Cannot read the metadata of ' . $file);
        }

        /** @var array<string, string> $metadata */
        $metadata = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

        return $metadata;
    }
}
