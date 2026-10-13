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

use coding_exception;
use context_system;
use core_text;
use local_dictionaries\api;
use local_dictionaries\event\domain_created;
use local_dictionaries\event\domain_updated;
use local_dictionaries\event\items_imported;
use moodle_exception;

/**
 * Write side of the reference dictionaries: domains, items and the CSV import.
 *
 * Write paths fail loudly (unlike {@see \local_dictionaries\resolver}, which degrades): a
 * missing table, an unknown domain or parent, or an over-long code raises an exception.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Maximum number of items one web service call may write or resolve. */
    public const MAX_BATCH_SIZE = 5000;

    /** @var int Width of the domain shortname column. */
    public const MAX_SHORTNAME_LENGTH = 100;

    /** @var int Width of the domain name column. */
    public const MAX_NAME_LENGTH = 255;

    /** @var int Number of rejected CSV rows the import report lists; the rest are only counted. */
    public const MAX_REJECTIONS_SHOWN = 20;

    /** @var int Number of keys per IN () lookup, well under every driver's placeholder limit. */
    private const LOOKUP_CHUNK = 1000;

    /** @var string CSV rejection: a cell is not valid UTF-8. */
    public const REJECT_INVALID_UTF8 = 'invalidutf8';

    /** @var string CSV rejection: the row has fewer columns than code and label need. */
    public const REJECT_TOO_FEW_COLUMNS = 'toofewcolumns';

    /** @var string CSV rejection: the code is empty. */
    public const REJECT_EMPTY_CODE = 'emptycode';

    /** @var string CSV rejection: the code is longer than the code column. */
    public const REJECT_CODE_TOO_LONG = 'codetoolong';

    /** @var string CSV rejection: the sortorder cell is not an integer. */
    public const REJECT_INVALID_SORTORDER = 'invalidsortorder';

    /** @var string CSV rejection: the active cell is neither 0 nor 1. */
    public const REJECT_INVALID_ACTIVE = 'invalidactive';

    /**
     * Return every domain with its item count, ordered by shortname.
     *
     * @return array Domain records keyed by id, each with an extra 'itemcount'.
     * @throws moodle_exception When the plugin tables are missing.
     */
    public function list_domains(): array {
        global $DB;

        $this->ensure_tables_available();
        $sql = 'SELECT d.*,
                       (SELECT COUNT(1)
                          FROM {' . domains::ITEM_TABLE . '} i
                         WHERE i.domainid = d.id) AS itemcount
                  FROM {' . domains::DOMAIN_TABLE . '} d
              ORDER BY d.shortname ASC';
        return $DB->get_records_sql($sql);
    }

    /**
     * Return the full record of one domain, or null when it does not exist.
     *
     * @param string $shortname
     * @return \stdClass|null
     * @throws moodle_exception When the plugin tables are missing.
     */
    public function get_domain(string $shortname): ?\stdClass {
        global $DB;

        $this->ensure_tables_available();
        $shortname = keys::domain($shortname);
        if ($shortname === '') {
            return null;
        }
        return $DB->get_record(domains::DOMAIN_TABLE, ['shortname' => $shortname]) ?: null;
    }

    /**
     * Create or update one domain.
     *
     * @param string $shortname Lowercase identifier matching the domain pattern.
     * @param string $name Display name; the shortname when empty.
     * @param string|null $description Null keeps the stored description; '' clears it.
     * @param int|null $visibility One of the api::VISIBILITY_* constants; null keeps the stored
     *     value (restricted for a new domain).
     * @return \stdClass The stored record.
     * @throws moodle_exception When the shortname is empty, invalid or too long.
     * @throws coding_exception When the visibility is not a known value.
     */
    public function upsert_domain(
        string $shortname,
        string $name,
        ?string $description = null,
        ?int $visibility = null
    ): \stdClass {
        global $DB;

        $this->ensure_tables_available();

        $raw = trim($shortname);
        if ($raw === '') {
            throw new moodle_exception('domainrequired', 'local_dictionaries');
        }
        if (core_text::strlen($raw) > self::MAX_SHORTNAME_LENGTH) {
            throw new moodle_exception('domaintoolong', 'local_dictionaries', '', self::MAX_SHORTNAME_LENGTH);
        }
        $shortname = keys::domain($raw);
        if ($shortname === '') {
            throw new moodle_exception('domaininvalid', 'local_dictionaries');
        }
        if (
            $visibility !== null
            && !in_array($visibility, [api::VISIBILITY_RESTRICTED, api::VISIBILITY_LOGGEDIN], true)
        ) {
            throw new coding_exception('Unknown domain visibility: ' . $visibility);
        }

        $name = trim($name);
        if ($name === '') {
            $name = $shortname;
        }
        $name = core_text::substr($name, 0, self::MAX_NAME_LENGTH);
        $setdescription = $description !== null;
        if ($setdescription) {
            $description = trim($description);
            $description = $description === '' ? null : $description;
        }

        $now = time();
        $existing = $DB->get_record(domains::DOMAIN_TABLE, ['shortname' => $shortname]);
        if ($existing) {
            // Only the changed columns: writing the whole row back could undo a revision bump
            // made by a concurrent item import, and old cache entries would match again.
            $update = new \stdClass();
            if ((string)$existing->name !== $name) {
                $update->name = $name;
            }
            $stored = $existing->description === null ? null : (string)$existing->description;
            if ($setdescription && $stored !== $description) {
                $update->description = $description;
            }
            if ($visibility !== null && (int)$existing->visibility !== $visibility) {
                $update->visibility = $visibility;
            }
            if ((array)$update !== []) {
                $update->id = (int)$existing->id;
                $update->timemodified = $now;
                $DB->update_record(domains::DOMAIN_TABLE, $update);
                domains::forget($shortname);
                domain_updated::create([
                    'context' => context_system::instance(),
                    'objectid' => (int)$existing->id,
                    'other' => ['shortname' => $shortname],
                ])->trigger();
            }
            return $DB->get_record(domains::DOMAIN_TABLE, ['id' => $existing->id], '*', MUST_EXIST);
        }

        $record = (object)[
            'shortname' => $shortname,
            'name' => $name,
            'description' => $description,
            'visibility' => $visibility ?? api::VISIBILITY_RESTRICTED,
            'revision' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = (int)$DB->insert_record(domains::DOMAIN_TABLE, $record);
        domain_created::create([
            'context' => context_system::instance(),
            'objectid' => $record->id,
            'other' => ['shortname' => $shortname],
        ])->trigger();

        return $DB->get_record(domains::DOMAIN_TABLE, ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Parse CSV content into items, reporting every row it cannot take.
     *
     * The content may start with a UTF-8 byte order mark, use ',', ';' or tab as delimiter
     * (detected on the first line) and quote multi-line fields. A first row naming the columns
     * 'code' and 'label' is a header; it may also name 'parentcode', 'sortorder' and 'active'
     * columns, in any order. Without a header the columns are code, label. A code written twice
     * keeps its last row, and each earlier row counts as a duplicate.
     *
     * @param string $csvcontent
     * @return array ['items' => list of item arrays for {@see self::upsert_items()},
     *     'rejected' => list of ['line' => int, 'reason' => one of the REJECT_* constants],
     *     'duplicates' => int]
     */
    public function parse_csv_content(string $csvcontent): array {
        $result = [
            'items' => [],
            'rejected' => [],
            'duplicates' => 0,
        ];

        $content = core_text::trim_utf8_bom($csvcontent);
        // Byte-level, without the u modifier: a file with invalid UTF-8 must still be split into rows.
        $content = preg_replace("/\r\n?/", "\n", $content) ?? $content;
        if (trim($content) === '') {
            return $result;
        }

        $delimiter = self::detect_delimiter($content);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $columns = ['code' => 0, 'label' => 1];
        $first = true;
        $line = 1;
        $offset = 0;
        $items = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $end = ftell($handle);
            $rowline = $line;
            $line += substr_count(substr($content, $offset, $end - $offset), "\n");
            $offset = $end;

            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }

            if ($first) {
                $first = false;
                $header = self::read_header($row);
                if ($header !== null) {
                    $columns = $header;
                    continue;
                }
            }

            $parsed = self::parse_row($row, $columns);
            if (is_string($parsed)) {
                $result['rejected'][] = ['line' => $rowline, 'reason' => $parsed];
                continue;
            }

            $codekey = keys::code_key($parsed['code']);
            if (isset($items[$codekey])) {
                $result['duplicates']++;
            }
            $items[$codekey] = $parsed;
        }
        fclose($handle);

        $result['items'] = array_values($items);
        return $result;
    }

    /**
     * Insert or update the items of one domain in one transaction.
     *
     * Each item is an array (or object) with 'code' and 'label' and, optionally, 'sortorder'
     * (int), 'active' (bool) and 'parentcode' (string; '' removes the parent). A key left out
     * keeps the stored value. Codes are matched case-insensitively and keep the spelling of the
     * last write. Parent codes are looked up in $parentdomain, or in the same domain when it is
     * null, and may name items of the same batch. Fires one items_imported event per call.
     *
     * @param string $domainshortname
     * @param array $items
     * @param string|null $parentdomain Shortname of the domain holding the parent items.
     * @return array ['inserted' => int, 'updated' => int, 'ignored' => int]
     * @throws moodle_exception On an unknown domain or parent, a self-parent or an over-long code.
     */
    public function upsert_items(string $domainshortname, array $items, ?string $parentdomain = null): array {
        $this->ensure_tables_available();
        $domain = $this->require_domain($domainshortname);
        $parent = $this->parent_domain($domain, $parentdomain);

        [$normalised, $ignored] = $this->normalise_items($items);
        $this->check_parents((int)$domain->id, (int)$parent->id, $normalised);
        $counts = $this->write_batch($domain, (int)$parent->id, $normalised);
        $counts['ignored'] += $ignored;

        if (!empty($items)) {
            $this->trigger_items_imported($domain, $counts);
        }
        return $counts;
    }

    /**
     * Insert or update items in bounded chunks, each in its own transaction.
     *
     * Every parent code is checked against the whole list before the first chunk is written,
     * so an unknown parent leaves the domain untouched. When the parents live in the imported
     * domain itself, the items are ordered so that a parent is written in the same chunk as its
     * child or in an earlier one (a chunk resolves parents from the database and its own
     * batch only), and a parent cycle is refused before any write. One items_imported event is fired per
     * committed chunk, with that chunk's counts (items dropped before any write count in the
     * first), so a failure in chunk N leaves an event for every chunk already committed. The web
     * services keep the hard MAX_BATCH_SIZE limit instead.
     *
     * @param string $domainshortname
     * @param array $items See {@see self::upsert_items()}.
     * @param int|null $chunksize Defaults to MAX_BATCH_SIZE.
     * @param string|null $parentdomain Shortname of the domain holding the parent items.
     * @return array ['inserted' => int, 'updated' => int, 'ignored' => int]
     * @throws coding_exception When the chunk size is not positive.
     * @throws moodle_exception On an unknown domain or parent, a self-parent, a parent cycle or an
     *     over-long code.
     */
    public function upsert_items_chunked(
        string $domainshortname,
        array $items,
        ?int $chunksize = null,
        ?string $parentdomain = null
    ): array {
        $chunksize = $chunksize ?? self::MAX_BATCH_SIZE;
        if ($chunksize < 1) {
            throw new coding_exception('Chunk size must be a positive integer.');
        }

        $this->ensure_tables_available();
        $domain = $this->require_domain($domainshortname);
        $parent = $this->parent_domain($domain, $parentdomain);

        [$normalised, $ignored] = $this->normalise_items($items);
        $this->check_parents((int)$domain->id, (int)$parent->id, $normalised);
        if ((int)$parent->id === (int)$domain->id) {
            $normalised = $this->order_parents_first($normalised);
        }

        $totals = [
            'inserted' => 0,
            'updated' => 0,
            'ignored' => $ignored,
        ];
        $pendingignored = $ignored;
        foreach (array_chunk($normalised, $chunksize, true) as $chunk) {
            $counts = $this->write_batch($domain, (int)$parent->id, $chunk);
            $totals['inserted'] += $counts['inserted'];
            $totals['updated'] += $counts['updated'];
            $totals['ignored'] += $counts['ignored'];
            // Items dropped before any write (no code, a superseded duplicate) ride on the first event.
            $counts['ignored'] += $pendingignored;
            $pendingignored = 0;
            $this->trigger_items_imported($domain, $counts);
        }
        if ($pendingignored > 0) {
            // Nothing was written, but the call did receive items.
            $this->trigger_items_imported($domain, $totals);
        }
        return $totals;
    }

    /**
     * Check whether one domain shortname exists.
     *
     * @param string $domainshortname
     * @return bool
     * @throws moodle_exception When the plugin tables are missing.
     */
    public function domain_exists(string $domainshortname): bool {
        return $this->get_domain($domainshortname) !== null;
    }

    /**
     * Normalise items and fold duplicates (last wins).
     *
     * @param array $items Raw items (arrays or objects).
     * @return array [map of codekey => normalised item, number of ignored items]
     * @throws moodle_exception When a code is longer than the code column.
     */
    private function normalise_items(array $items): array {
        $normalised = [];
        $ignored = 0;
        foreach ($items as $position => $item) {
            $item = $this->normalise_item($item);
            if ($item === null) {
                $ignored++;
                continue;
            }
            if (core_text::strlen($item['code']) > keys::MAX_KEY_LENGTH) {
                throw new moodle_exception('errorcodetoolong', 'local_dictionaries', '', (int)$position + 1);
            }
            $codekey = keys::code_key($item['code']);
            if (isset($normalised[$codekey])) {
                // An earlier item with the same code is superseded by this one.
                $ignored++;
            }
            $item['codekey'] = $codekey;
            $normalised[$codekey] = $item;
        }
        return [$normalised, $ignored];
    }

    /**
     * Normalise one item to its canonical keys, or null when it carries no code.
     *
     * @param mixed $item
     * @return array|null ['code', 'label', 'searchkey', 'sortorder' (int|null),
     *     'active' (int|null), 'parentkey' (string|null: null keeps, '' clears)]
     */
    private function normalise_item($item): ?array {
        if (is_object($item)) {
            $item = (array)$item;
        }
        if (!is_array($item)) {
            return null;
        }

        $code = keys::clean_code((string)($item['code'] ?? ''));
        if ($code === '') {
            return null;
        }
        // Control characters go, except tab, LF and CR, which a multi-line label may carry.
        $label = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($item['label'] ?? '')) ?? '');

        $sortorder = null;
        if (isset($item['sortorder']) && $item['sortorder'] !== '') {
            $sortorder = (int)$item['sortorder'];
        }
        $active = null;
        if (isset($item['active']) && $item['active'] !== '') {
            $active = empty($item['active']) ? 0 : 1;
        }
        $parentkey = null;
        if (array_key_exists('parentcode', $item) && $item['parentcode'] !== null) {
            $parentkey = keys::code_key(keys::clean_code((string)$item['parentcode']));
        }

        return [
            'code' => $code,
            'label' => $label,
            'searchkey' => keys::search_key($label),
            'sortorder' => $sortorder,
            'active' => $active,
            'parentkey' => $parentkey,
        ];
    }

    /**
     * Refuse a batch whose parent codes name no item, name the item itself or close a cycle.
     *
     * Runs before the first write of both write paths. A parent counts as present when it is
     * stored in the parent domain or, when the parent domain is the item's own domain, when it is
     * part of the batch.
     *
     * @param int $domainid
     * @param int $parentdomainid
     * @param array $normalised Map of codekey => normalised item.
     * @return void
     * @throws moodle_exception On an unknown parent, a self-parent or a parent cycle.
     */
    private function check_parents(int $domainid, int $parentdomainid, array $normalised): void {
        $parentkeys = [];
        foreach ($normalised as $codekey => $item) {
            if ($item['parentkey'] === null || $item['parentkey'] === '') {
                continue;
            }
            if ($parentdomainid === $domainid && $item['parentkey'] === (string)$codekey) {
                throw new moodle_exception('errorparentself', 'local_dictionaries', '', $item['code']);
            }
            $parentkeys[$item['parentkey']] = $item['parentkey'];
        }
        if (empty($parentkeys)) {
            return;
        }

        $missing = array_diff_key($parentkeys, $this->fetch_ids_by_key($parentdomainid, array_values($parentkeys)));
        if ($parentdomainid === $domainid) {
            $missing = array_diff_key($missing, $normalised);
        }
        if (!empty($missing)) {
            throw new moodle_exception(
                'errorunknownparent',
                'local_dictionaries',
                '',
                implode(', ', array_slice(array_values($missing), 0, 10))
            );
        }
        $this->check_parent_cycles($domainid, $parentdomainid, $normalised);
    }

    /**
     * Refuse a batch that would leave a parent cycle in the stored items.
     *
     * The parent column may point into another domain, so a cycle can run through two domains
     * and the check does not depend on the parent domain. The graph a write would leave is
     * followed from every item the batch gives a parent: an item of the batch takes its NEW
     * parent (a cleared one ends the chain), any other item keeps its stored parentid. The stored
     * links are read one level at a time with a single query per level and never twice, so the
     * reads are bounded by the items the chains reach, and the visited sets end a walk that runs
     * into a loop that was already stored. A new item can only close a loop through batch links.
     *
     * @param int $domainid
     * @param int $parentdomainid
     * @param array $normalised Map of codekey => normalised item.
     * @return void
     * @throws moodle_exception When a parent chain returns to itself.
     */
    private function check_parent_cycles(int $domainid, int $parentdomainid, array $normalised): void {
        global $DB;

        $parentkeys = [];
        foreach ($normalised as $item) {
            if ($item['parentkey'] !== null && $item['parentkey'] !== '') {
                $parentkeys[$item['parentkey']] = $item['parentkey'];
            }
        }
        if (empty($parentkeys)) {
            // Only clears: removing links cannot close a loop.
            return;
        }

        // A node is a stored id, or 'n:<codekey>' for an item the batch inserts.
        $existing = $this->fetch_ids_by_key($domainid, array_map('strval', array_keys($normalised)));
        $parentids = $this->fetch_ids_by_key($parentdomainid, array_values($parentkeys));
        $nodes = [];
        foreach (array_keys($normalised) as $codekey) {
            $nodes[(string)$codekey] = $existing[(string)$codekey] ?? ('n:' . $codekey);
        }

        $links = [];
        $codes = [];
        foreach ($normalised as $codekey => $item) {
            if ($item['parentkey'] === null) {
                continue;
            }
            $node = $nodes[(string)$codekey];
            $codes[$node] = $item['code'];
            if ($item['parentkey'] === '') {
                $links[$node] = null;
            } else if ($parentdomainid === $domainid && isset($nodes[$item['parentkey']])) {
                $links[$node] = $nodes[$item['parentkey']];
            } else {
                $links[$node] = $parentids[$item['parentkey']];
            }
        }

        // Read the stored parent of every node a chain reaches that the batch does not relink.
        $stored = [];
        $frontier = [];
        foreach ($links as $target) {
            if (is_int($target) && !isset($links[$target])) {
                $frontier[$target] = $target;
            }
        }
        while (!empty($frontier)) {
            $fetched = [];
            foreach (array_chunk($frontier, self::LOOKUP_CHUNK) as $chunk) {
                [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'itemid');
                $rows = $DB->get_records_select(domains::ITEM_TABLE, "id $insql", $params, '', 'id, parentid');
                foreach ($chunk as $id) {
                    $fetched[$id] = null;
                }
                foreach ($rows as $row) {
                    $fetched[(int)$row->id] = $row->parentid === null ? null : (int)$row->parentid;
                }
            }
            $stored += $fetched;
            $frontier = [];
            foreach ($fetched as $parent) {
                if ($parent !== null && !array_key_exists($parent, $stored) && !isset($links[$parent])) {
                    $frontier[$parent] = $parent;
                }
            }
        }

        $acyclic = [];
        foreach (array_keys($links) as $start) {
            $path = [];
            $node = $start;
            while ($node !== null && !isset($acyclic[$node])) {
                if (isset($path[$node])) {
                    throw new moodle_exception('errorparentcycle', 'local_dictionaries', '', $codes[$start]);
                }
                $path[$node] = true;
                $node = array_key_exists($node, $links) ? $links[$node] : ($stored[$node] ?? null);
            }
            $acyclic += $path;
        }
    }

    /**
     * Order a batch so that every item follows its parent when the parent is in the batch too.
     *
     * Stable: file order is kept otherwise. Each item has one parent, so the chain above an item
     * is walked up to the first item already placed and emitted root first. Only meaningful when
     * the parent domain is the imported domain. Cycles are refused beforehand by
     * {@see self::check_parent_cycles()}.
     *
     * @param array $normalised Map of codekey => normalised item.
     * @return array The same map, parents first.
     */
    private function order_parents_first(array $normalised): array {
        $ordered = [];
        foreach (array_keys($normalised) as $start) {
            $path = [];
            $key = $start;
            while ($key !== null && !isset($ordered[$key])) {
                if (isset($path[$key])) {
                    // Unreachable after check_parent_cycles(); stops the walk if it ever runs without it.
                    break;
                }
                $path[$key] = true;
                $parentkey = $normalised[$key]['parentkey'];
                $key = ($parentkey !== null && $parentkey !== '' && isset($normalised[$parentkey])) ? $parentkey : null;
            }
            foreach (array_reverse(array_keys($path)) as $placed) {
                $ordered[$placed] = $normalised[$placed];
            }
        }
        return $ordered;
    }

    /**
     * Write one batch of normalised items in one transaction and bump the domain revision.
     *
     * @param \stdClass $domain
     * @param int $parentdomainid
     * @param array $normalised Map of codekey => normalised item, already checked.
     * @return array ['inserted' => int, 'updated' => int, 'ignored' => int]
     * @throws moodle_exception When the database refuses a code as a duplicate of a stored one.
     */
    private function write_batch(\stdClass $domain, int $parentdomainid, array $normalised): array {
        global $DB;

        $counts = [
            'inserted' => 0,
            'updated' => 0,
            'ignored' => 0,
        ];
        if (empty($normalised)) {
            return $counts;
        }

        $domainid = (int)$domain->id;
        $existing = $this->fetch_items_by_key($domainid, array_map('strval', array_keys($normalised)));
        $now = time();
        $state = [];
        $ids = [];

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($normalised as $codekey => $item) {
                $codekey = (string)$codekey;
                if (isset($existing[$codekey])) {
                    $row = $existing[$codekey];
                    $ids[$codekey] = (int)$row->id;
                    $update = $this->item_changes($row, $item);
                    if ($update === null) {
                        $state[$codekey] = 'ignored';
                        continue;
                    }
                    $update->id = (int)$row->id;
                    $update->timemodified = $now;
                    $DB->update_record(domains::ITEM_TABLE, $update);
                    $state[$codekey] = 'updated';
                    continue;
                }

                $record = (object)[
                    'domainid' => $domainid,
                    'code' => $item['code'],
                    'codekey' => $codekey,
                    'label' => $item['label'],
                    'searchkey' => $item['searchkey'],
                    'sortorder' => $item['sortorder'] ?? 0,
                    'active' => $item['active'] ?? 1,
                    'parentid' => null,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                try {
                    $ids[$codekey] = (int)$DB->insert_record(domains::ITEM_TABLE, $record);
                } catch (\dml_write_exception $e) {
                    // The code key folds case and accents; a collation can fold more (MariaDB
                    // reads 'ß' as 'ss'), so name the code instead of a database error.
                    throw new moodle_exception('errorcodecollision', 'local_dictionaries', '', $item['code']);
                }
                $state[$codekey] = 'inserted';
            }

            // Parents last, so an item may name a parent written earlier in the same batch.
            $this->write_parents($normalised, $existing, $ids, $state, $parentdomainid, $now);

            foreach ($state as $value) {
                $counts[$value]++;
            }
            if ($counts['inserted'] > 0 || $counts['updated'] > 0) {
                $DB->execute(
                    'UPDATE {' . domains::DOMAIN_TABLE . '}
                        SET revision = revision + 1, timemodified = :now
                      WHERE id = :id',
                    ['now' => $now, 'id' => $domainid]
                );
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        domains::forget((string)$domain->shortname);
        return $counts;
    }

    /**
     * Set or clear the parent of every item of a batch that names one.
     *
     * @param array $normalised Map of codekey => normalised item.
     * @param array $existing Map of codekey => stored row, as read before the batch.
     * @param array $ids Map of codekey => item id, for every item of the batch.
     * @param array $state Map of codekey => 'inserted'|'updated'|'ignored'; an ignored item whose
     *     parent changes becomes 'updated'.
     * @param int $parentdomainid
     * @param int $now
     * @return void
     * @throws moodle_exception When a parent code names no item.
     */
    private function write_parents(
        array $normalised,
        array $existing,
        array $ids,
        array &$state,
        int $parentdomainid,
        int $now
    ): void {
        global $DB;

        $parentkeys = [];
        foreach ($normalised as $item) {
            if ($item['parentkey'] !== null && $item['parentkey'] !== '') {
                $parentkeys[$item['parentkey']] = $item['parentkey'];
            }
        }
        $parentids = $parentkeys ? $this->fetch_ids_by_key($parentdomainid, array_values($parentkeys)) : [];

        foreach ($normalised as $codekey => $item) {
            $codekey = (string)$codekey;
            if ($item['parentkey'] === null) {
                continue;
            }
            $newparent = null;
            if ($item['parentkey'] !== '') {
                if (!isset($parentids[$item['parentkey']])) {
                    throw new moodle_exception('errorunknownparent', 'local_dictionaries', '', $item['parentkey']);
                }
                $newparent = $parentids[$item['parentkey']];
            }
            $current = isset($existing[$codekey]) && $existing[$codekey]->parentid !== null
                ? (int)$existing[$codekey]->parentid
                : null;
            if ($current === $newparent) {
                continue;
            }
            $DB->set_field(domains::ITEM_TABLE, 'parentid', $newparent, ['id' => $ids[$codekey]]);
            if ($state[$codekey] === 'ignored') {
                $DB->set_field(domains::ITEM_TABLE, 'timemodified', $now, ['id' => $ids[$codekey]]);
                $state[$codekey] = 'updated';
            }
        }
    }

    /**
     * Compute the columns an incoming item changes on a stored row, or null when it changes none.
     *
     * @param \stdClass $row Stored item.
     * @param array $item Normalised incoming item.
     * @return \stdClass|null
     */
    private function item_changes(\stdClass $row, array $item): ?\stdClass {
        $update = new \stdClass();
        if ((string)$row->code !== $item['code']) {
            $update->code = $item['code'];
        }
        if ((string)$row->label !== $item['label']) {
            $update->label = $item['label'];
            $update->searchkey = $item['searchkey'];
        }
        if ($item['sortorder'] !== null && (int)$row->sortorder !== $item['sortorder']) {
            $update->sortorder = $item['sortorder'];
        }
        if ($item['active'] !== null && (int)$row->active !== $item['active']) {
            $update->active = $item['active'];
        }
        return (array)$update === [] ? null : $update;
    }

    /**
     * Fetch stored items of one domain by code key, keyed by their stored code key.
     *
     * Callers look rows up by exact key: a collation that folds more than lowercase (MariaDB
     * folds accents) may return a row stored under another key, which no lookup then finds.
     *
     * @param int $domainid
     * @param array $codekeys List of code keys.
     * @return array Map of codekey => record.
     */
    private function fetch_items_by_key(int $domainid, array $codekeys): array {
        global $DB;

        $found = [];
        foreach (array_chunk($codekeys, self::LOOKUP_CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'codekey');
            $params['domainid'] = $domainid;
            $rows = $DB->get_records_select(
                domains::ITEM_TABLE,
                "domainid = :domainid AND codekey $insql",
                $params,
                '',
                'id, code, codekey, label, sortorder, active, parentid'
            );
            foreach ($rows as $row) {
                $found[(string)$row->codekey] = $row;
            }
        }
        return $found;
    }

    /**
     * Fetch item ids of one domain by code key.
     *
     * @param int $domainid
     * @param array $codekeys List of code keys.
     * @return array Map of codekey => int id.
     */
    private function fetch_ids_by_key(int $domainid, array $codekeys): array {
        $ids = [];
        foreach ($this->fetch_items_by_key($domainid, $codekeys) as $codekey => $row) {
            $ids[$codekey] = (int)$row->id;
        }
        return $ids;
    }

    /**
     * Return a domain record or throw the localised unknown-domain error.
     *
     * @param string $shortname
     * @return \stdClass
     * @throws moodle_exception When the domain does not exist.
     */
    private function require_domain(string $shortname): \stdClass {
        $domain = $this->get_domain($shortname);
        if (!$domain) {
            throw new moodle_exception('errorunknowndomain', 'local_dictionaries', '', $shortname);
        }
        return $domain;
    }

    /**
     * Return the domain holding parent items: the named one, or the item's own domain.
     *
     * @param \stdClass $domain
     * @param string|null $parentdomain
     * @return \stdClass
     * @throws moodle_exception When the named parent domain does not exist.
     */
    private function parent_domain(\stdClass $domain, ?string $parentdomain): \stdClass {
        if ($parentdomain === null || trim($parentdomain) === '') {
            return $domain;
        }
        return $this->require_domain($parentdomain);
    }

    /**
     * Fire the items_imported event of one import.
     *
     * @param \stdClass $domain
     * @param array $counts ['inserted', 'updated', 'ignored']
     * @return void
     */
    private function trigger_items_imported(\stdClass $domain, array $counts): void {
        items_imported::create([
            'context' => context_system::instance(),
            'objectid' => (int)$domain->id,
            'other' => [
                'shortname' => (string)$domain->shortname,
                'inserted' => (int)$counts['inserted'],
                'updated' => (int)$counts['updated'],
                'ignored' => (int)$counts['ignored'],
            ],
        ])->trigger();
    }

    /**
     * Throw when the plugin tables are missing: write paths fail loudly on a partial install.
     *
     * @return void
     * @throws moodle_exception When a table is missing.
     */
    private function ensure_tables_available(): void {
        if (!domains::tables_available()) {
            throw new moodle_exception('errornotables', 'local_dictionaries');
        }
    }

    /**
     * Recognise a header row and map its column names to positions.
     *
     * @param array $row
     * @return array|null Map of column name => position, or null when the row is data.
     */
    private static function read_header(array $row): ?array {
        $columns = [];
        foreach ($row as $position => $cell) {
            $name = core_text::strtolower(trim((string)$cell));
            if (in_array($name, ['code', 'label', 'parentcode', 'sortorder', 'active'], true) && !isset($columns[$name])) {
                $columns[$name] = (int)$position;
            }
        }
        if (!isset($columns['code'], $columns['label'])) {
            return null;
        }
        return $columns;
    }

    /**
     * Turn one CSV row into an item, or return the reason it is rejected.
     *
     * @param array $row
     * @param array $columns Map of column name => position.
     * @return array|string The item, or one of the REJECT_* constants.
     */
    private static function parse_row(array $row, array $columns) {
        foreach ($row as $cell) {
            if ($cell !== null && !mb_check_encoding((string)$cell, 'UTF-8')) {
                return self::REJECT_INVALID_UTF8;
            }
        }
        if (count($row) < max(2, $columns['code'] + 1, $columns['label'] + 1)) {
            return self::REJECT_TOO_FEW_COLUMNS;
        }

        $code = keys::clean_code((string)$row[$columns['code']]);
        if ($code === '') {
            return self::REJECT_EMPTY_CODE;
        }
        if (core_text::strlen($code) > keys::MAX_KEY_LENGTH) {
            return self::REJECT_CODE_TOO_LONG;
        }

        $item = [
            'code' => $code,
            'label' => trim((string)$row[$columns['label']]),
        ];
        if (isset($columns['sortorder'])) {
            $value = trim((string)($row[$columns['sortorder']] ?? ''));
            if ($value !== '') {
                if (!preg_match('/^-?\d{1,9}$/', $value)) {
                    return self::REJECT_INVALID_SORTORDER;
                }
                $item['sortorder'] = (int)$value;
            }
        }
        if (isset($columns['active'])) {
            $value = trim((string)($row[$columns['active']] ?? ''));
            if ($value !== '') {
                if ($value !== '0' && $value !== '1') {
                    return self::REJECT_INVALID_ACTIVE;
                }
                $item['active'] = (int)$value;
            }
        }
        if (isset($columns['parentcode'])) {
            $item['parentcode'] = (string)($row[$columns['parentcode']] ?? '');
        }
        return $item;
    }

    /**
     * Pick the delimiter of the first non-empty line: the most frequent of ',', ';' and tab outside quotes.
     *
     * Ties and lines with none of them fall back to ',' first, then ';'.
     *
     * @param string $content Content with LF line endings.
     * @return string
     */
    private static function detect_delimiter(string $content): string {
        $firstline = '';
        foreach (explode("\n", $content) as $candidate) {
            if (trim($candidate) !== '') {
                $firstline = $candidate;
                break;
            }
        }

        $counts = [',' => 0, ';' => 0, "\t" => 0];
        $inquotes = false;
        $length = strlen($firstline);
        for ($i = 0; $i < $length; $i++) {
            $char = $firstline[$i];
            if ($char === '"') {
                $inquotes = !$inquotes;
            } else if (!$inquotes && isset($counts[$char])) {
                $counts[$char]++;
            }
        }

        $best = ',';
        foreach ($counts as $delimiter => $count) {
            if ($count > $counts[$best]) {
                $best = $delimiter;
            }
        }
        return (string)$best;
    }
}
