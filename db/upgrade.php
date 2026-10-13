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
 * Upgrade steps of local_dictionaries.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_dictionaries_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042001) {
        require_once(__DIR__ . '/upgradelib.php');

        // Every DDL call is guarded so a step interrupted halfway (its earlier DDL stays applied) can run again.
        $domain = new xmldb_table('local_dictionaries_domain');
        $fields = [
            new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name'),
            new xmldb_field('visibility', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'description'),
            new xmldb_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'visibility'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($domain, $field)) {
                $dbman->add_field($domain, $field);
            }
        }

        // The key columns are declared without a default, as install.xml has them (XMLDB rewrites an empty
        // string default on a NOT NULL char column to none and raises a debugging message); the DDL generator
        // fills existing rows with an empty string, which the backfill below replaces.
        $item = new xmldb_table('local_dictionaries_item');
        $keyfields = [
            new xmldb_field('codekey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'code'),
            new xmldb_field('searchkey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'label'),
        ];
        $otherfields = [
            new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'searchkey'),
            new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'sortorder'),
            new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'active'),
        ];
        foreach (array_merge($keyfields, $otherfields) as $field) {
            if (!$dbman->field_exists($item, $field)) {
                $dbman->add_field($item, $field);
            }
        }

        local_dictionaries_backfill_item_keys();
        foreach (local_dictionaries_merge_codekey_collisions() as $message) {
            mtrace($message);
        }

        foreach ($keyfields as $field) {
            $field->setDefault(null);
            $dbman->change_field_default($item, $field);
        }

        $olduix = new xmldb_index('domainid_code_uix', XMLDB_INDEX_UNIQUE, ['domainid', 'code']);
        if ($dbman->index_exists($item, $olduix)) {
            $dbman->drop_index($item, $olduix);
        }
        $newuix = new xmldb_index('domainid_codekey_uix', XMLDB_INDEX_UNIQUE, ['domainid', 'codekey']);
        if (!$dbman->index_exists($item, $newuix)) {
            $dbman->add_index($item, $newuix);
        }
        $searchix = new xmldb_index('domainid_searchkey_ix', XMLDB_INDEX_NOTUNIQUE, ['domainid', 'searchkey']);
        if (!$dbman->index_exists($item, $searchix)) {
            $dbman->add_index($item, $searchix);
        }
        // The foreign key creates its own index (named by core); that index is what tells a re-run it is done.
        $parentix = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
        if (!$dbman->find_index_name($item, $parentix)) {
            $parentkey = new xmldb_key('parentid_fk', XMLDB_KEY_FOREIGN, ['parentid'], 'local_dictionaries_item', ['id']);
            $dbman->add_key($item, $parentkey);
        }

        upgrade_plugin_savepoint(true, 2026042001, 'local', 'dictionaries');
    }

    return true;
}
