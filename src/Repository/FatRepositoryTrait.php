<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

trait FatRepositoryTrait
{
    /**
     * Per-instance schema existence memo, keyed by prefixed table name.
     *
     * Deliberately NOT static: a static cache would survive for the whole
     * process, so one repository instance could poison another's answer and a
     * unit test could never exercise both branches of the guard. Instances are
     * per-request under PHP-FPM, so a per-instance cache is still one probe.
     *
     * @var array<string,bool>
     */
    private $__table_exists_cache = [];

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

    /**
     * Escape a Y-m-d date, rejecting anything else.
     *
     * Filter dates are optional on the HRM pages, and FA's date_cells() writes
     * today's date into an empty date field, so an "unset" filter would
     * otherwise arrive here as a real value and silently narrow the result set.
     * Anything that is not a well formed Y-m-dd becomes '' (= no filter).
     *
     * Note: escape() follows FA's convention and returns a PRE-QUOTED SQL
     * literal, so the result is inlined without adding quotes of its own.
     *
     * @param string $value
     *
     * @return string quoted date literal, or '' when unset/invalid
     *
     * @since 1.0.0
     */
    protected function dateVal($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        return $this->escape($value);
    }

    /**
     * Does a (prefixed) table exist in the current company schema?
     *
     * Some HRM read paths LEFT JOIN onto masters that are owned by modules which
     * are NOT part of this deployment (notably 0_leave_types, documented as
     * ksf_FA_Leave's master). MariaDB refuses a query that names a missing
     * table even inside a LEFT JOIN, so those joins must be probed before they
     * are emitted. Results are memoised per request.
     *
     * @param string $name Unprefixed table name (e.g. 'leave_types')
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function tableExists(string $name): bool
    {
        $key = TB_PREF . $name;
        if (array_key_exists($key, $this->__table_exists_cache)) {
            return $this->__table_exists_cache[$key];
        }
        $sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = " . $this->escape($key);
        $row = $this->dbFetchAssoc(@db_query($sql, false));
        $this->__table_exists_cache[$key] = $row ? ((int) $row['cnt'] > 0) : false;
        return $this->__table_exists_cache[$key];
    }
}
