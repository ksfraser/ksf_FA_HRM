<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

trait FatRepositoryTrait
{
    protected function dbQuery(string $sql)
    {
        return db_query($sql);
    }

    protected function dbFetchAssoc($result): ?array
    {
        if ($result && db_num_rows($result)) {
            return db_fetch_assoc($result);
        }
        return null;
    }

    protected function dbFetchAll($result): array
    {
        $rows = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    protected function dbInsertId(): int
    {
        return (int)db_insert_id();
    }

    protected function dbNumRows($result): int
    {
        return $result ? db_num_rows($result) : 0;
    }

    protected function escape($value): string
    {
        return db_escape($value);
    }

    protected function intVal($value): int
    {
        return (int)$value;
    }

    protected function floatVal($value): float
    {
        return (float)$value;
    }
}
