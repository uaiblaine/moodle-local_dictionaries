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

namespace local_dictionaries\local;

use cache;

/**
 * Table availability and domain rows, shared by the read paths and the manager.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class domains {
    /** @var string Domain table name. */
    public const DOMAIN_TABLE = 'local_dictionaries_domain';

    /** @var string Item table name. */
    public const ITEM_TABLE = 'local_dictionaries_item';

    /** @var bool|null Table availability; only true is memoised, so a finished install is seen at once. */
    private static ?bool $tablesavailable = null;

    /**
     * Whether both plugin tables exist.
     *
     * @return bool
     */
    public static function tables_available(): bool {
        global $DB;

        if (self::$tablesavailable === true) {
            return true;
        }

        $dbman = $DB->get_manager();
        $available = $dbman->table_exists(self::DOMAIN_TABLE) && $dbman->table_exists(self::ITEM_TABLE);
        if ($available) {
            self::$tablesavailable = true;
        }
        return $available;
    }

    /**
     * Return the recorded row of one domain (id, shortname, visibility, revision), or null.
     *
     * Read through a request cache: one query per existing domain and request (a missing domain
     * is not cached). The caller must have checked {@see self::tables_available()} and
     * normalised the shortname.
     *
     * @param string $shortname A shortname already normalised by {@see keys::domain()}.
     * @return \stdClass|null
     */
    public static function get(string $shortname): ?\stdClass {
        global $DB;

        if ($shortname === '') {
            return null;
        }

        $cache = self::cache();
        if ($cache) {
            try {
                $cached = $cache->get($shortname);
                if (is_array($cached)) {
                    return (object)$cached;
                }
            } catch (\Throwable $e) {
                debugging('local_dictionaries: domain cache get failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $record = $DB->get_record(
            self::DOMAIN_TABLE,
            ['shortname' => $shortname],
            'id, shortname, visibility, revision'
        );
        if (!$record) {
            return null;
        }

        $row = [
            'id' => (int)$record->id,
            'shortname' => (string)$record->shortname,
            'visibility' => (int)$record->visibility,
            'revision' => (int)$record->revision,
        ];
        if ($cache) {
            try {
                $cache->set($shortname, $row);
            } catch (\Throwable $e) {
                debugging('local_dictionaries: domain cache set failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return (object)$row;
    }

    /**
     * Drop the request-cached row of one domain after a write changed it.
     *
     * @param string $shortname
     * @return void
     */
    public static function forget(string $shortname): void {
        $cache = self::cache();
        if (!$cache) {
            return;
        }
        try {
            $cache->delete($shortname);
        } catch (\Throwable $e) {
            debugging('local_dictionaries: domain cache delete failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The request cache of domain rows, or null when the cache layer cannot be built.
     *
     * @return cache|null
     */
    private static function cache(): ?cache {
        try {
            return cache::make('local_dictionaries', 'domains');
        } catch (\Throwable $e) {
            debugging('local_dictionaries: domain cache unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
