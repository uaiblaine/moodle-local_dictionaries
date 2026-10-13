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

use context_system;
use local_dictionaries\local\domains;
use local_dictionaries\local\keys;

/**
 * Public search API over the reference dictionaries.
 *
 * Like {@see resolver}, {@see self::search()} runs no capability check and degrades to an empty
 * result when the tables are missing. The rule that decides who may read a domain is public:
 * {@see self::can_read()} and {@see self::require_read()}; the web services gate through the latter.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var int Domain visibility: readable through the web services with local/dictionaries:view only. */
    public const VISIBILITY_RESTRICTED = 0;

    /** @var int Domain visibility: readable through the web services by any logged-in user who is not a guest. */
    public const VISIBILITY_LOGGEDIN = 1;

    /** @var int Default number of search results. */
    public const DEFAULT_SEARCH_LIMIT = 20;

    /** @var int Largest number of search results one call may ask for. */
    public const MAX_SEARCH_LIMIT = 100;

    /**
     * Whether a user may browse and search one domain.
     *
     * The rule is decided on the domain's recorded visibility: VISIBILITY_LOGGEDIN is readable
     * by any logged-in user who is not a guest; any other value, an unknown or invalid
     * shortname included, needs local/dictionaries:view at system level, so an unprivileged
     * caller cannot tell a restricted domain from a missing one.
     *
     * @param string $domain Domain shortname as received.
     * @param int|null $userid User to check; null for the current user.
     * @return bool
     */
    public static function can_read(string $domain, ?int $userid = null): bool {
        if (self::is_open($domain)) {
            if ($userid === null) {
                return isloggedin() && !isguestuser();
            }
            return $userid > 0 && !isguestuser($userid);
        }
        return has_capability('local/dictionaries:view', context_system::instance(), $userid);
    }

    /**
     * Require the current user to be allowed to browse and search one domain.
     *
     * See {@see self::can_read()} for the rule. Call it before {@see self::search()} and before
     * any listing of a domain's items; resolving codes the viewer already sees (stored values
     * in a record the viewer may read) through {@see resolver} needs no gate.
     *
     * @param string $domain Domain shortname as received.
     * @return void
     * @throws \moodle_exception When the domain is open and the current user is not logged in or is the guest.
     * @throws \required_capability_exception When the domain is restricted or unknown and the
     *     user lacks local/dictionaries:view.
     */
    public static function require_read(string $domain): void {
        if (self::is_open($domain)) {
            if (!isloggedin() || isguestuser()) {
                throw new \moodle_exception('noguest');
            }
            return;
        }
        require_capability('local/dictionaries:view', context_system::instance());
    }

    /**
     * Whether the domain exists and is recorded as open to logged-in users.
     *
     * @param string $domain Domain shortname as received.
     * @return bool
     */
    private static function is_open(string $domain): bool {
        $shortname = keys::domain($domain);
        $record = ($shortname !== '' && domains::tables_available()) ? domains::get($shortname) : null;
        return $record && (int)$record->visibility === self::VISIBILITY_LOGGEDIN;
    }

    /**
     * Search the active items of one domain.
     *
     * Runs no capability check: a caller that lets a user browse a domain calls
     * {@see self::require_read()} first (and again for $parentdomain).
     *
     * Order of the results: an item whose code equals the query, then items whose search key
     * starts with the folded query, then items that contain it; inside each group by sortorder,
     * then by search key (the label folded to lowercase without accents), then by code. An empty
     * query lists the domain from the start of that order. Inactive items are never offered.
     *
     * @param string $domain Domain shortname.
     * @param string $query Text typed by the user.
     * @param int $limit Maximum number of items, clamped to 1..MAX_SEARCH_LIMIT.
     * @param string|null $parentcode When set, only items whose parent item has this code.
     * @param string|null $parentdomain Domain holding the parent items. The schema records a
     *     parent by item id only, so the caller names the domain; null means the searched
     *     domain itself. An unknown parent domain gives an empty result. Ignored without $parentcode.
     * @return array ['items' => list of ['code' => string, 'label' => string], 'hasmore' => bool]
     */
    public static function search(
        string $domain,
        string $query,
        int $limit = self::DEFAULT_SEARCH_LIMIT,
        ?string $parentcode = null,
        ?string $parentdomain = null
    ): array {
        global $DB;

        $empty = ['items' => [], 'hasmore' => false];
        $domain = keys::domain($domain);
        if ($domain === '' || !domains::tables_available()) {
            return $empty;
        }
        $record = domains::get($domain);
        if (!$record) {
            return $empty;
        }

        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $base = 'i.domainid = :domainid AND i.active = 1';
        $baseparams = ['domainid' => (int)$record->id];
        $join = '';
        if ($parentcode !== null && trim($parentcode) !== '') {
            $parentrecord = ($parentdomain === null || trim($parentdomain) === '')
                ? $record
                : domains::get(keys::domain($parentdomain));
            if (!$parentrecord) {
                return $empty;
            }
            $join = 'JOIN {' . domains::ITEM_TABLE . '} p ON p.id = i.parentid';
            $base .= ' AND p.domainid = :parentdomainid AND p.codekey = :parentkey';
            $baseparams['parentdomainid'] = (int)$parentrecord->id;
            $baseparams['parentkey'] = keys::code_key(keys::clean_code($parentcode));
        }

        $found = [];
        $codekey = keys::code_key(keys::clean_code($query));
        if ($codekey !== '') {
            self::collect(
                $found,
                $join,
                $base . ' AND i.codekey = :codekey',
                $baseparams + ['codekey' => $codekey],
                $limit + 1
            );
        }

        $searchkey = keys::search_key($query);
        if ($searchkey === '') {
            self::collect($found, $join, $base, $baseparams, $limit + 1);
        } else {
            $escaped = $DB->sql_like_escape($searchkey);
            self::collect(
                $found,
                $join,
                $base . ' AND ' . $DB->sql_like('i.searchkey', ':prefix'),
                $baseparams + ['prefix' => $escaped . '%'],
                $limit + 1
            );
            self::collect(
                $found,
                $join,
                $base . ' AND ' . $DB->sql_like('i.searchkey', ':infix')
                    . ' AND ' . $DB->sql_like('i.searchkey', ':notprefix', true, true, true),
                $baseparams + ['infix' => '%' . $escaped . '%', 'notprefix' => $escaped . '%'],
                $limit + 1
            );
        }

        $items = [];
        foreach (array_slice($found, 0, $limit) as $row) {
            $items[] = [
                'code' => (string)$row->code,
                'label' => (string)$row->label,
            ];
        }
        return [
            'items' => $items,
            'hasmore' => count($found) > $limit,
        ];
    }

    /**
     * Append the rows of one search group to the result, skipping rows already found.
     *
     * Stops querying once the result holds more than the limit asks for, which is all
     * 'hasmore' needs to know.
     *
     * @param array $found Rows found so far, keyed by item id; extended in place.
     * @param string $join Optional parent join.
     * @param string $where
     * @param array $params
     * @param int $wanted Number of rows the result should reach (limit + 1).
     * @return void
     */
    private static function collect(array &$found, string $join, string $where, array $params, int $wanted): void {
        global $DB;

        $missing = $wanted - count($found);
        if ($missing <= 0) {
            return;
        }

        $sql = 'SELECT i.id, i.code, i.label, i.sortorder, i.searchkey, i.codekey
                  FROM {' . domains::ITEM_TABLE . '} i
                  ' . $join . '
                 WHERE ' . $where . '
              ORDER BY i.sortorder ASC, i.searchkey ASC, i.codekey ASC';
        // Rows already found can reappear in a later group, so read that many extra.
        $rows = $DB->get_records_sql($sql, $params, 0, $missing + count($found));
        foreach ($rows as $row) {
            if (count($found) >= $wanted) {
                break;
            }
            $found[(int)$row->id] = $row;
        }
    }
}
