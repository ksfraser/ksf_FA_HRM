<?php

/**
 * Shared test stubs for ksf_FA_HRM.
 *
 * Provides FA globals (TB_PREF, ST_SALESINVOICE), a GLOBALS-backed fake
 * database (db_query/db_fetch_assoc/db_num_rows/db_insert_id/db_escape),
 * base classes for loading hooks.php in tests (hooks, application), and
 * stub traits for the ksf_FA_Common namespace that hooks.php references.
 *
 * @package ksf_FA_HRM
 */

namespace {
    if (!defined('TB_PREF')) {
        define('TB_PREF', '0_');
    }

    if (!defined('ST_SALESINVOICE')) {
        define('ST_SALESINVOICE', 10);
    }

    if (!function_exists('db_query')) {
        /**
         * Records the last SQL; SELECT pops the next seeded result set,
         * INSERT/WRITE succeed.
         *
         * @param string $sql SQL statement
         * @param string $msg Optional error message
         * @return mixed Seeded result array or true
         */
        function db_query($sql, $msg = '')
        {
            $GLOBALS['__fa_last_sql'] = $sql;
            $prefix = strtolower(substr(ltrim((string)$sql), 0, 6));
            if ($prefix === 'select') {
                if (isset($GLOBALS['__fa_select_queue']) && count($GLOBALS['__fa_select_queue']) > 0) {
                    $GLOBALS['__fa_current_result'] = array_shift($GLOBALS['__fa_select_queue']);
                } else {
                    $GLOBALS['__fa_current_result'] = $GLOBALS['__fa_select_result'] ?? [];
                }
                return $GLOBALS['__fa_current_result'];
            }
            if ($prefix === 'insert') {
                $GLOBALS['__fa_last_insert_id'] = $GLOBALS['__fa_next_id'] ?? 1;
                $GLOBALS['__fa_next_id'] = $GLOBALS['__fa_last_insert_id'] + 1;
            }
            return true;
        }
    }

    if (!function_exists('db_fetch_assoc')) {
        /**
         * Shifts the next row off the current seeded result set.
         *
         * @param mixed $result Seeded result
         * @return array|false Next row or false when exhausted
         */
        function db_fetch_assoc($result)
        {
            if (isset($GLOBALS['__fa_current_result']) && count($GLOBALS['__fa_current_result']) > 0) {
                return array_shift($GLOBALS['__fa_current_result']);
            }
            return false;
        }
    }

    if (!function_exists('db_num_rows')) {
        /**
         * Returns the number of rows in a seeded result.
         *
         * @param mixed $result Seeded result
         * @return int Row count
         */
        function db_num_rows($result)
        {
            if (is_array($result)) {
                return count($result);
            }
            return (int)$result ? 1 : 0;
        }
    }

    if (!function_exists('db_insert_id')) {
        /**
         * Returns the last inserted id assigned by the fake DB.
         *
         * @return int Last insert id
         */
        function db_insert_id()
        {
            return $GLOBALS['__fa_last_insert_id'] ?? 1;
        }
    }

    if (!function_exists('db_escape')) {
        /**
         * Escapes a value for SQL in the fake DB.
         *
         * @param mixed $value Value to escape
         * @return string Escaped value
         */
        function db_escape($value)
        {
            return addslashes((string)$value);
        }
    }

    if (!class_exists('hooks', false)) {
        /**
         * Minimal FrontAccounting base hooks class for tests.
         */
        class hooks
        {
            public $module_name = '';
            public $version = '';
        }
    }

    if (!class_exists('application', false)) {
        /**
         * Minimal FrontAccounting base application class for tests.
         */
        class application
        {
        }
    }

    if (!function_exists('_')) {
        /**
         * Translation stub.
         *
         * @param string $text Text to translate
         * @return string Untranslated text
         */
        function _($text)
        {
            return $text;
        }
    }

    if (!function_exists('set_ext_domain')) {
        /**
         * No-op extension domain setter for tests.
         *
         * @param string|null $domain Extension domain
         * @return void
         */
        function set_ext_domain($domain = null)
        {
        }
    }
}

namespace ksfraser\FrontAccounting\Common\Traits {

    if (!trait_exists('ksfraser\FrontAccounting\Common\Traits\WorkflowHooksTrait', false)) {
        /**
         * Stub trait replacing the ksf_FA_Common workflow hooks trait in tests.
         */
        trait WorkflowHooksTrait
        {
        }
    }

    if (!trait_exists('ksfraser\FrontAccounting\Common\Traits\CrudOperationsTrait', false)) {
        /**
         * Stub trait replacing the ksf_FA_Common CRUD operations trait in tests.
         */
        trait CrudOperationsTrait
        {
        }
    }
}
