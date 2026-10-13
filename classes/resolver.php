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
use local_dictionaries\local\domains;
use local_dictionaries\local\keys;

/**
 * Resolve codes into labels: the public read API that consumer plugins call.
 *
 * The method names and signatures and {@see self::DOMAIN_PATTERN} are a contract with
 * consumers that probe this class at runtime (profilefield_repeatable). The resolver runs no
 * capability check, by design: resolving codes the viewer already sees (stored values in a
 * record the viewer may read) needs no gate; browsing or searching a domain does, through
 * {@see api::require_read()}. It degrades to "no labels" when the tables are missing or the
 * cache layer fails, so a partial install never takes a consumer's page down.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolver {
    /** @var string Pattern every domain shortname matches; consumers read it instead of copying it. */
    public const DOMAIN_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * Resolve one code to its label.
     *
     * Codes match ignoring case and accents: 'ABC' finds the item stored as 'abc'.
     *
     * @param string $domain Domain shortname.
     * @param string $code
     * @return string|null The raw label (the caller escapes it), or null when unknown.
     */
    public static function resolve(string $domain, string $code): ?string {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $labels = self::resolve_bulk($domain, [$code]);
        return $labels[$code] ?? null;
    }

    /**
     * Resolve many codes of one domain.
     *
     * The result is keyed by the caller's spelling of each code (trimmed), so a caller holding
     * 'ABC' finds its label even when the item was written as 'abc'. Unknown codes are absent.
     *
     * @param string $domain Domain shortname.
     * @param array $codes List of codes (strings).
     * @return array Map of caller code => raw label.
     */
    public static function resolve_bulk(string $domain, array $codes): array {
        global $DB;

        $domain = keys::domain($domain);
        if ($domain === '' || empty($codes) || !domains::tables_available()) {
            return [];
        }

        // Caller spelling => code key; several spellings may share one key.
        $callerkeys = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code === '') {
                continue;
            }
            $callerkeys[$code] = keys::code_key(keys::clean_code($code));
        }
        if (empty($callerkeys)) {
            return [];
        }

        $record = domains::get($domain);
        if (!$record) {
            return [];
        }

        $cache = self::cache();
        $labelsbykey = [];
        $cachekeys = [];
        foreach (array_unique(array_values($callerkeys)) as $codekey) {
            $cachekeys[$codekey] = self::build_cache_key((int)$record->id, (int)$record->revision, $codekey);
        }

        $cached = $cache ? self::cache_get_many_safe($cache, array_values($cachekeys)) : [];
        $missing = [];
        foreach ($cachekeys as $codekey => $cachekey) {
            $value = $cached[$cachekey] ?? false;
            if ($value === false) {
                $missing[] = (string)$codekey;
                continue;
            }
            $labelsbykey[$codekey] = (string)$value;
        }

        if (!empty($missing)) {
            [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'codekey');
            $params['domainid'] = (int)$record->id;
            $rows = $DB->get_records_select(
                domains::ITEM_TABLE,
                "domainid = :domainid AND codekey $insql",
                $params,
                '',
                'id, codekey, label'
            );
            foreach ($rows as $row) {
                $codekey = (string)$row->codekey;
                // A collation that folds more than the code key does (MariaDB's utf8mb4_unicode_ci
                // reads 'ß' as 'ss') can return a row for a key nobody asked for; only exact key
                // matches count.
                if (!isset($cachekeys[$codekey])) {
                    continue;
                }
                $labelsbykey[$codekey] = (string)$row->label;
                if ($cache) {
                    self::cache_set_safe($cache, $cachekeys[$codekey], (string)$row->label);
                }
            }
        }

        $results = [];
        foreach ($callerkeys as $code => $codekey) {
            if (array_key_exists($codekey, $labelsbykey)) {
                $results[(string)$code] = $labelsbykey[$codekey];
            }
        }
        return $results;
    }

    /**
     * Return whether one domain exists.
     *
     * @param string $domain Domain shortname.
     * @return bool
     */
    public static function domain_exists(string $domain): bool {
        $domain = keys::domain($domain);
        if ($domain === '' || !domains::tables_available()) {
            return false;
        }
        return domains::get($domain) !== null;
    }

    /**
     * Build the cache key of one label.
     *
     * The revision makes every write invalidate the domain's labels without a purge; the hash
     * keeps the key within the simple-key alphabet whatever characters the code holds.
     *
     * @param int $domainid
     * @param int $revision
     * @param string $codekey
     * @return string
     */
    private static function build_cache_key(int $domainid, int $revision, string $codekey): string {
        return 'l' . $domainid . '_' . $revision . '_' . sha1($codekey);
    }

    /**
     * The label cache, or null when the cache layer cannot be built.
     *
     * @return cache|null
     */
    private static function cache(): ?cache {
        try {
            return cache::make('local_dictionaries', 'labels');
        } catch (\Throwable $e) {
            debugging('local_dictionaries: label cache unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Read multiple keys from cache, returning an empty map on failure.
     *
     * @param cache $cache
     * @param array $keys List of cache keys.
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
     * @return void
     */
    private static function cache_set_safe(cache $cache, string $key, string $value): void {
        try {
            $cache->set($key, $value);
        } catch (\Throwable $e) {
            debugging('local_dictionaries: cache set failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
