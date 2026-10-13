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
use coding_exception;
use core_text;
use moodle_exception;

/**
 * Manager for reference domains and items.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Maximum batch size for upsert/resolve. */
    public const MAX_BATCH_SIZE = 5000;

    /** @var string Domain table name. */
    private const DOMAIN_TABLE = 'local_dictionaries_domain';

    /** @var string Item table name. */
    private const ITEM_TABLE = 'local_dictionaries_item';

    /** @var string Domain shortname pattern. */
    private const DOMAIN_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * Return list of available domains.
     *
     * @return array
     */
    public function list_domains(): array {
        global $DB;

        $this->ensure_tables_available();
        return $DB->get_records(self::DOMAIN_TABLE, [], 'shortname ASC');
    }

    /**
     * Create or update one domain.
     *
     * @param string $shortname
     * @param string $name
     * @return \stdClass
     */
    public function upsert_domain(string $shortname, string $name): \stdClass {
        global $DB;

        $this->ensure_tables_available();
        $domaintable = self::DOMAIN_TABLE;

        $shortname = $this->normalise_domain_shortname($shortname);
        if ($shortname === '') {
            throw new moodle_exception('domainrequired', 'local_dictionaries');
        }

        $name = trim($name);
        if ($name === '') {
            $name = $shortname;
        }

        $existing = $DB->get_record($domaintable, ['shortname' => $shortname]);
        $now = time();

        if ($existing) {
            if ((string)$existing->name !== $name) {
                $existing->name = $name;
                $existing->timemodified = $now;
                $DB->update_record($domaintable, $existing);
            }
            return $existing;
        }

        $record = (object)[
            'shortname' => $shortname,
            'name' => $name,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = (int)$DB->insert_record($domaintable, $record);

        return $record;
    }

    /**
     * Parse CSV content into code/label list.
     *
     * Accepted format: code,label with optional header line.
     *
     * @param string $csvcontent
     * @return array
     */
    public function parse_csv_content(string $csvcontent): array {
        $csvcontent = trim($csvcontent);
        if ($csvcontent === '') {
            return [];
        }

        $items = [];
        $lines = preg_split('/\R/u', $csvcontent) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line, ',', '"', '\\');
            if (count($row) < 2) {
                continue;
            }

            $code = trim((string)($row[0] ?? ''));
            $label = trim((string)($row[1] ?? ''));

            if ($index === 0 && core_text::strtolower($code) === 'code' && core_text::strtolower($label) === 'label') {
                continue;
            }

            $items[] = [
                'code' => $code,
                'label' => $label,
            ];
        }

        return $items;
    }

    /**
     * Upsert code-label pairs for one domain.
     *
     * @param string $domainshortname
     * @param array $items
     * @return array
     */
    public function upsert_items(string $domainshortname, array $items): array {
        global $DB;

        $this->ensure_tables_available();
        $itemtable = self::ITEM_TABLE;

        $domain = $this->get_domain_by_shortname($domainshortname);
        if (!$domain) {
            throw new moodle_exception('errorunknowndomain', 'local_dictionaries', '', $domainshortname);
        }

        $counts = [
            'inserted' => 0,
            'updated' => 0,
            'ignored' => 0,
        ];

        if (empty($items)) {
            return $counts;
        }

        $normaliseditems = [];
        foreach ($items as $item) {
            $item = $this->normalise_item($item);
            $code = $item['code'];
            $label = $item['label'];
            if ($code === '') {
                $counts['ignored']++;
                continue;
            }
            $normaliseditems[$code] = [
                'code' => $code,
                'label' => $label,
            ];
        }

        if (empty($normaliseditems)) {
            return $counts;
        }

        $codes = array_keys($normaliseditems);
        [$insql, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED, 'code');
        $params['domainid'] = (int)$domain->id;

        $existingrecords = $DB->get_records_select(
            $itemtable,
            "domainid = :domainid AND code $insql",
            $params,
            '',
            'id, code, label'
        );

        $existingbycode = [];
        foreach ($existingrecords as $record) {
            $existingbycode[(string)$record->code] = $record;
        }

        $now = time();

        $transaction = $DB->start_delegated_transaction();

        foreach ($normaliseditems as $code => $item) {
            if (isset($existingbycode[$code])) {
                $existing = $existingbycode[$code];
                if ((string)$existing->label === $item['label']) {
                    $counts['ignored']++;
                    continue;
                }

                $DB->update_record($itemtable, (object)[
                    'id' => (int)$existing->id,
                    'label' => $item['label'],
                    'timemodified' => $now,
                ]);
                $counts['updated']++;
                continue;
            }

            $DB->insert_record($itemtable, (object)[
                'domainid' => (int)$domain->id,
                'code' => $item['code'],
                'label' => $item['label'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $counts['inserted']++;
        }

        $transaction->allow_commit();

        if ($counts['inserted'] > 0 || $counts['updated'] > 0) {
            $this->purge_label_cache();
        }

        return $counts;
    }

    /**
     * Upsert code-label pairs in bounded chunks, merging result counts.
     *
     * Each chunk runs in its own transaction via {@see upsert_items()}, so
     * arbitrarily large imports (admin CSV upload) never build one huge
     * transaction. The web services keep the hard MAX_BATCH_SIZE limit.
     *
     * @param string $domainshortname
     * @param array $items
     * @param int|null $chunksize Defaults to MAX_BATCH_SIZE.
     * @return array{inserted: int, updated: int, ignored: int}
     * @throws coding_exception When the chunk size is not positive.
     * @throws moodle_exception When the domain is unknown.
     */
    public function upsert_items_chunked(string $domainshortname, array $items, ?int $chunksize = null): array {
        $chunksize = $chunksize ?? self::MAX_BATCH_SIZE;
        if ($chunksize < 1) {
            throw new coding_exception('Chunk size must be a positive integer.');
        }

        if (empty($items)) {
            return $this->upsert_items($domainshortname, $items);
        }

        $totals = [
            'inserted' => 0,
            'updated' => 0,
            'ignored' => 0,
        ];

        foreach (array_chunk($items, $chunksize) as $chunk) {
            $counts = $this->upsert_items($domainshortname, $chunk);
            $totals['inserted'] += $counts['inserted'];
            $totals['updated'] += $counts['updated'];
            $totals['ignored'] += $counts['ignored'];
        }

        return $totals;
    }

    /**
     * Check whether one domain shortname exists.
     *
     * @param string $domainshortname
     * @return bool
     */
    public function domain_exists(string $domainshortname): bool {
        global $DB;

        $this->ensure_tables_available();
        $domainshortname = $this->normalise_domain_shortname($domainshortname);
        if ($domainshortname === '') {
            return false;
        }

        return $DB->record_exists(self::DOMAIN_TABLE, ['shortname' => $domainshortname]);
    }

    /**
     * Resolve one domain by shortname.
     *
     * @param string $domainshortname
     * @return \stdClass|false
     */
    private function get_domain_by_shortname(string $domainshortname) {
        global $DB;

        $domainshortname = $this->normalise_domain_shortname($domainshortname);
        if ($domainshortname === '') {
            return false;
        }

        return $DB->get_record(self::DOMAIN_TABLE, ['shortname' => $domainshortname]);
    }

    /**
     * Ensure plugin tables already exist.
     *
     * Write paths fail loudly on partial installs, unlike the resolver
     * which silently degrades to unresolved labels.
     */
    private function ensure_tables_available(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $available =
            $dbman->table_exists(new \xmldb_table(self::DOMAIN_TABLE)) &&
            $dbman->table_exists(new \xmldb_table(self::ITEM_TABLE));

        if (!$available) {
            throw new coding_exception(get_string('referencenotables', 'local_dictionaries'));
        }
    }

    /**
     * Normalise and validate domain shortname.
     *
     * @param string $domainshortname
     * @return string
     */
    private function normalise_domain_shortname(string $domainshortname): string {
        $domainshortname = core_text::strtolower(trim($domainshortname));
        if ($domainshortname === '' || !preg_match(self::DOMAIN_PATTERN, $domainshortname)) {
            return '';
        }
        return $domainshortname;
    }

    /**
     * Purge label cache.
     */
    private function purge_label_cache(): void {
        try {
            cache::make('local_dictionaries', 'labels')->purge();
        } catch (\Throwable $e) {
            debugging('local_dictionaries: cache purge failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Normalise one upsert item to canonical code/label keys.
     *
     * Strips control characters (except common whitespace in label) and trims values.
     * Truncates code to 255 chars to fit the schema; labels are stored as TEXT.
     *
     * @param mixed $item
     * @return array{code: string, label: string}
     */
    private function normalise_item($item): array {
        if (is_object($item)) {
            $item = (array)$item;
        }

        if (!is_array($item)) {
            return [
                'code' => '',
                'label' => '',
            ];
        }

        $code = trim((string)($item['code'] ?? ''));
        $label = trim((string)($item['label'] ?? ''));

        // Strip ASCII control characters from code (no whitespace allowed inside code).
        $code = preg_replace('/[\x00-\x1F\x7F]/u', '', $code) ?? '';
        // Strip control chars from label except tab, LF, CR.
        $label = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $label) ?? '';

        if (core_text::strlen($code) > 255) {
            $code = core_text::substr($code, 0, 255);
        }

        return [
            'code' => $code,
            'label' => $label,
        ];
    }
}
