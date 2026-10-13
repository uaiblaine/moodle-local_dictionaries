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

/**
 * Helpers of the upgrade steps, kept apart from db/upgrade.php so tests can call them.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/local/keys.php');

/**
 * Fill codekey and searchkey on every item that has none, with the functions the runtime uses.
 *
 * Rows are read and written in chunks of $chunksize ids, each chunk in its own transaction, so a
 * large dictionary never holds one transaction or one result set. Idempotent: a row whose keys
 * are already filled is not selected.
 *
 * @param int $chunksize Rows per chunk.
 * @return int The number of rows updated.
 */
function local_dictionaries_backfill_item_keys(int $chunksize = 500): int {
    global $DB;

    $updated = 0;
    $lastid = 0;
    do {
        $rows = [];
        $rs = $DB->get_recordset_select(
            'local_dictionaries_item',
            "id > :lastid AND (codekey = :nokey OR searchkey = :nosearch)",
            ['lastid' => $lastid, 'nokey' => '', 'nosearch' => ''],
            'id ASC',
            'id, code, label',
            0,
            $chunksize
        );
        foreach ($rs as $row) {
            $rows[] = $row;
        }
        $rs->close();
        if (!$rows) {
            break;
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($rows as $row) {
            $DB->update_record('local_dictionaries_item', (object) [
                'id' => $row->id,
                'codekey' => \local_dictionaries\local\keys::code_key(\local_dictionaries\local\keys::clean_code($row->code)),
                'searchkey' => \local_dictionaries\local\keys::search_key($row->label),
            ]);
            $updated++;
            $lastid = (int) $row->id;
        }
        $transaction->allow_commit();
    } while (count($rows) === $chunksize);

    return $updated;
}

/**
 * Merge items of one domain whose codekeys are equal, so the unique (domainid, codekey) index can be added.
 *
 * PostgreSQL stored codes that differ only in case or accents as separate items; the new key folds
 * them into one. In each group the most recently modified item (highest id on a tie) survives and
 * the others are deleted; children of a deleted item are re-parented to the survivor and the
 * domain revision is bumped so cached labels are not served from the old rows.
 *
 * @param int $chunksize Groups handled per transaction.
 * @return array One human-readable line per deleted item, for the caller to mtrace().
 */
function local_dictionaries_merge_codekey_collisions(int $chunksize = 200): array {
    global $DB;

    $messages = [];
    $groups = $DB->get_records_sql(
        "SELECT MIN(id) AS id, domainid, codekey
           FROM {local_dictionaries_item}
       GROUP BY domainid, codekey
         HAVING COUNT(1) > 1
       ORDER BY domainid, codekey"
    );

    foreach (array_chunk($groups, $chunksize) as $chunk) {
        $transaction = $DB->start_delegated_transaction();
        foreach ($chunk as $group) {
            $items = $DB->get_records(
                'local_dictionaries_item',
                ['domainid' => $group->domainid, 'codekey' => $group->codekey],
                '',
                'id, code, timemodified'
            );
            uasort($items, static function (stdClass $a, stdClass $b): int {
                return [(int) $b->timemodified, (int) $b->id] <=> [(int) $a->timemodified, (int) $a->id];
            });
            $keeper = array_shift($items);
            foreach ($items as $item) {
                $DB->set_field('local_dictionaries_item', 'parentid', $keeper->id, ['parentid' => $item->id]);
                $DB->delete_records('local_dictionaries_item', ['id' => $item->id]);
                // Codes are stored raw and mtrace() echoes into the web upgrade page: escape them.
                $messages[] = 'local_dictionaries: domain ' . (int)$group->domainid . ": merged code '" . s($item->code)
                    . "' (item " . (int)$item->id . ") into '" . s($keeper->code) . "' (item " . (int)$keeper->id . ')';
            }
            $DB->execute(
                "UPDATE {local_dictionaries_domain} SET revision = revision + 1 WHERE id = :id",
                ['id' => $group->domainid]
            );
        }
        $transaction->allow_commit();
    }

    return $messages;
}
