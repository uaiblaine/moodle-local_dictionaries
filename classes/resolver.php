<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dictionaries;

use cache;
use core_text;

/**
 * Resolve code values into labels using local reference tables.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolver {
    /** @var string Domain table name. */
    private const DOMAIN_TABLE = 'local_dictionaries_domain';

    /** @var string Item table name. */
    private const ITEM_TABLE = 'local_dictionaries_item';

    /** @var string Domain shortname pattern. */
    private const DOMAIN_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * Resolve one code label.
     *
     * @param string $domain
     * @param string $code
     * @return string|null
     */
    public static function resolve(string $domain, string $code): ?string {
        $code = trim((string)$code);
        if ($code === '') {
            return null;
        }

        $labels = self::resolve_bulk($domain, [$code]);
        return $labels[$code] ?? null;
    }

    /**
     * Resolve many code labels in one domain.
     *
     * @param string $domain
     * @param string[] $codes
     * @return array
     */
    public static function resolve_bulk(string $domain, array $codes): array {
        global $DB;

        $domain = self::normalise_domain($domain);
        if ($domain === '' || empty($codes) || !self::tables_available()) {
            return [];
        }

        $normalisedcodes = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code === '') {
                continue;
            }
            $normalisedcodes[$code] = $code;
        }

        if (empty($normalisedcodes)) {
            return [];
        }

        $cache = cache::make('local_dictionaries', 'labels');
        $cachekeys = [];
        foreach ($normalisedcodes as $code) {
            $cachekeys[$code] = self::build_cache_key($domain, $code);
        }

        $results = [];
        $missingcodes = [];
        $cached = self::cache_get_many_safe($cache, array_values($cachekeys));
        foreach ($cachekeys as $code => $cachekey) {
            $value = $cached[$cachekey] ?? false;
            if ($value === false) {
                $missingcodes[] = $code;
                continue;
            }
            $results[$code] = (string)$value;
        }

        if (empty($missingcodes)) {
            return $results;
        }

        $domainid = $DB->get_field(self::DOMAIN_TABLE, 'id', ['shortname' => $domain]);
        if (!$domainid) {
            return $results;
        }

        [$insql, $params] = $DB->get_in_or_equal($missingcodes, SQL_PARAMS_NAMED, 'code');
        $params['domainid'] = (int)$domainid;

        $records = $DB->get_records_select(
            self::ITEM_TABLE,
            "domainid = :domainid AND code $insql",
            $params,
            '',
            'code, label'
        );

        foreach ($records as $record) {
            $code = (string)$record->code;
            $label = (string)$record->label;
            $results[$code] = $label;
            self::cache_set_safe($cache, self::build_cache_key($domain, $code), $label);
        }

        return $results;
    }

    /**
     * Return whether one domain exists.
     *
     * @param string $domain
     * @return bool
     */
    public static function domain_exists(string $domain): bool {
        global $DB;

        $domain = self::normalise_domain($domain);
        if ($domain === '' || !self::tables_available()) {
            return false;
        }

        return $DB->record_exists(self::DOMAIN_TABLE, ['shortname' => $domain]);
    }

    /** @var bool|null Static cache for table availability (only caches true). */
    private static ?bool $tablesavailable = null;

    /**
     * Check if required DB tables are available.
     *
     * Resolution degrades to "no labels" instead of throwing so a partial
     * install can never take profile rendering down.
     *
     * @return bool
     */
    private static function tables_available(): bool {
        global $DB;

        if (self::$tablesavailable === true) {
            return true;
        }

        $dbman = $DB->get_manager();
        $available =
            $dbman->table_exists(self::DOMAIN_TABLE) &&
            $dbman->table_exists(self::ITEM_TABLE);
        if ($available) {
            self::$tablesavailable = true;
        }

        return $available;
    }

    /**
     * Build cache key for domain+code pair.
     *
     * @param string $domain
     * @param string $code
     * @return string
     */
    private static function build_cache_key(string $domain, string $code): string {
        return $domain . ':' . $code;
    }

    /**
     * Read multiple keys from cache, returning empty map on failure.
     *
     * @param cache $cache
     * @param string[] $keys
     * @return array
     */
    private static function cache_get_many_safe(cache $cache, array $keys): array {
        try {
            return $cache->get_many($keys);
        } catch (\Throwable $e) {
            debugging('local_dictionaries: cache get_many failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Write one cache entry and swallow cache layer failures.
     *
     * @param cache $cache
     * @param string $key
     * @param string $value
     */
    private static function cache_set_safe(cache $cache, string $key, string $value): void {
        try {
            $cache->set($key, $value);
        } catch (\Throwable $e) {
            debugging('local_dictionaries: cache set failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Normalise domain shortname.
     *
     * @param string $domain
     * @return string
     */
    private static function normalise_domain(string $domain): string {
        $domain = core_text::strtolower(trim($domain));
        if ($domain === '' || !preg_match(self::DOMAIN_PATTERN, $domain)) {
            return '';
        }

        return $domain;
    }
}
