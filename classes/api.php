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
     * List every domain, for a form that offers them in a select.
     *
     * Runs NO capability check, like {@see resolver}: the listing exposes the name, description
     * and visibility of every domain, restricted ones included, so a caller shows it only to
     * users who configure fields (an admin page or a definition form behind its own capability),
     * never on a learner-facing surface. Degrades to an empty list when the plugin tables are
     * missing. Two queries however many domains exist: the domains, then one grouped query over
     * the items.
     *
     * Each entry is an array with:
     * - shortname (string);
     * - name (string): raw, the consumer applies format_string();
     * - description (string): raw, '' when none;
     * - visibility (int): one of the VISIBILITY_* constants;
     * - itemcount (int): active items only (inactive items still resolve but are not offered);
     * - parentdomain (string|null): the shortname of the domain that holds the parents of the
     *   domain's items. Every item of the domain that has a parent (active or not) is looked at:
     *   when all of those parents sit in one domain, that domain is named (the domain itself for
     *   a tree inside it); null when no item has a parent or the parents sit in more than one
     *   domain. Items without a parent do not count against the rule.
     *
     * Ordered by name, then shortname.
     *
     * @return array List of the arrays described above.
     */
    public static function list_domains(): array {
        global $DB;

        if (!domains::tables_available()) {
            return [];
        }
        $records = $DB->get_records(
            domains::DOMAIN_TABLE,
            null,
            'name ASC, shortname ASC',
            'id, shortname, name, description, visibility'
        );
        if (!$records) {
            return [];
        }

        // One row per (domain, parent domain); a NULL parentid gives parent domain 0.
        $sql = 'SELECT i.domainid, COALESCE(p.domainid, 0) AS parentdomainid,
                       COUNT(1) AS total,
                       SUM(CASE WHEN i.active = 1 THEN 1 ELSE 0 END) AS activecount
                  FROM {' . domains::ITEM_TABLE . '} i
             LEFT JOIN {' . domains::ITEM_TABLE . '} p ON p.id = i.parentid
              GROUP BY i.domainid, COALESCE(p.domainid, 0)';
        $itemcounts = [];
        $parentdomains = [];
        $rows = $DB->get_recordset_sql($sql);
        foreach ($rows as $row) {
            $domainid = (int)$row->domainid;
            $itemcounts[$domainid] = ($itemcounts[$domainid] ?? 0) + (int)$row->activecount;
            if ((int)$row->parentdomainid > 0) {
                $parentdomains[$domainid][] = (int)$row->parentdomainid;
            }
        }
        $rows->close();

        $list = [];
        foreach ($records as $record) {
            $id = (int)$record->id;
            $parents = $parentdomains[$id] ?? [];
            $parentrecord = count($parents) === 1 ? ($records[$parents[0]] ?? null) : null;
            $list[] = [
                'shortname' => (string)$record->shortname,
                'name' => (string)$record->name,
                'description' => (string)($record->description ?? ''),
                'visibility' => (int)$record->visibility,
                'itemcount' => $itemcounts[$id] ?? 0,
                'parentdomain' => $parentrecord ? (string)$parentrecord->shortname : null,
            ];
        }
        return $list;
    }

    /**
     * Look up many codes of one domain, with what a consumer needs to validate a stored value.
     *
     * For server-side validation of values a consumer has already decided to accept from this
     * user (a form the user may submit): it runs NO capability check and lists inactive items
     * too, so the consumer decides what an inactive code means. Never feed its result to a
     * listing or a search box; {@see self::search()} after {@see self::require_read()} does that.
     *
     * Codes match like {@see resolver::resolve_bulk()}: ignoring case and accents, the result
     * keyed by the caller's spelling of each code (trimmed); unknown codes are absent. Each entry
     * is an array with:
     * - code (string): the code as stored;
     * - label (string): raw, the consumer escapes it;
     * - active (bool);
     * - parentcode (string|null): the stored code of the item's parent, null without one;
     * - parentdomain (string|null): the shortname of the domain holding that parent.
     *
     * One query for the items and one for their parents, uncached (validation must read the
     * current state). Degrades to an empty array when the plugin tables are missing.
     *
     * @param string $domain Domain shortname.
     * @param array $codes List of codes (strings).
     * @return array Map of caller code => the array described above.
     */
    public static function lookup(string $domain, array $codes): array {
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

        $wanted = array_flip(array_values($callerkeys));
        [$insql, $params] = $DB->get_in_or_equal(array_keys($wanted), SQL_PARAMS_NAMED, 'codekey');
        $params['domainid'] = (int)$record->id;
        $rows = $DB->get_records_select(
            domains::ITEM_TABLE,
            "domainid = :domainid AND codekey $insql",
            $params,
            '',
            'id, code, codekey, label, active, parentid'
        );
        $bykey = [];
        $parentids = [];
        foreach ($rows as $row) {
            // A collation that folds more than the code key does (MariaDB reads 'ß' as 'ss') can
            // return a row for a key nobody asked for; it stays unread because the lookup below
            // uses the caller's key, not the row's.
            $bykey[(string)$row->codekey] = $row;
            if (!empty($row->parentid)) {
                $parentids[(int)$row->parentid] = true;
            }
        }

        $parents = [];
        if ($parentids) {
            [$pinsql, $pparams] = $DB->get_in_or_equal(array_keys($parentids), SQL_PARAMS_NAMED, 'parentid');
            $parents = $DB->get_records_sql(
                'SELECT p.id, p.code, d.shortname
                   FROM {' . domains::ITEM_TABLE . '} p
                   JOIN {' . domains::DOMAIN_TABLE . '} d ON d.id = p.domainid
                  WHERE p.id ' . $pinsql,
                $pparams
            );
        }

        $results = [];
        foreach ($callerkeys as $code => $codekey) {
            $row = $bykey[$codekey] ?? null;
            if (!$row) {
                continue;
            }
            $parent = empty($row->parentid) ? null : ($parents[(int)$row->parentid] ?? null);
            $results[(string)$code] = [
                'code' => (string)$row->code,
                'label' => (string)$row->label,
                'active' => (int)$row->active === 1,
                'parentcode' => $parent ? (string)$parent->code : null,
                'parentdomain' => $parent ? (string)$parent->shortname : null,
            ];
        }
        return $results;
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
