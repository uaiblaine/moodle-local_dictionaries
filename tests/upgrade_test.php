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

/**
 * Runs the real 0.4.1 to 0.5.0 upgrade step on the schema 0.4.1 shipped.
 *
 * The two tables are dropped and recreated from tests/fixtures/install_041.xml (a copy of 0.4.1's
 * db/install.xml with only PATH changed), 0.4.1 data is inserted, xmldb_local_dictionaries_upgrade()
 * runs, and the result is compared with db/install.xml. The tables are rebuilt from db/install.xml
 * afterwards because DDL is not rolled back by resetAfterTest().
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     ::xmldb_local_dictionaries_upgrade
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    /** @var bool Whether the tables were rebuilt and must be restored. */
    private bool $staged = false;

    /**
     * Drop both tables and create them again from an install.xml file.
     *
     * @param string $path Absolute path of the XMLDB file.
     */
    private function rebuild_tables(string $path): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_dictionaries_item', 'local_dictionaries_domain'] as $name) {
            $table = new \xmldb_table($name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        $dbman->install_from_xmldb_file($path);
    }

    /**
     * Put the current schema back, whatever the test left.
     */
    protected function tearDown(): void {
        global $CFG;
        if ($this->staged) {
            $this->rebuild_tables($CFG->dirroot . '/local/dictionaries/db/install.xml');
        }
        parent::tearDown();
    }

    /**
     * Insert a 0.4.1 domain.
     *
     * @param string $shortname
     * @return int The domain id.
     */
    private function domain(string $shortname): int {
        global $DB;
        return (int) $DB->insert_record('local_dictionaries_domain', (object) [
            'shortname' => $shortname,
            'name' => $shortname,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /**
     * Insert a 0.4.1 item.
     *
     * @param int $domainid
     * @param string $code
     * @param string $label
     * @param int $timemodified
     * @return int The item id.
     */
    private function item(int $domainid, string $code, string $label, int $timemodified): int {
        global $DB;
        return (int) $DB->insert_record('local_dictionaries_item', (object) [
            'domainid' => $domainid,
            'code' => $code,
            'label' => $label,
            'timecreated' => 1,
            'timemodified' => $timemodified,
        ]);
    }

    /**
     * The upgrade migrates 0.4.1 data and leaves exactly the schema install.xml declares.
     */
    public function test_upgrade_from_041_reaches_the_installed_schema(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/dictionaries/db/upgrade.php');
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $this->staged = true;
        $this->rebuild_tables(__DIR__ . '/fixtures/install_041.xml');
        $this->assertFalse($dbman->field_exists('local_dictionaries_item', 'searchkey'));

        $dom1 = $this->domain('dom_a');
        $dom2 = $this->domain('dom_b');
        $this->item($dom1, 'SP', 'São Paulo', 5);
        $this->item($dom1, 'Ação', 'Ação  Imediata', 5);
        $other = $this->item($dom2, 'sao', 'Other domain', 5);
        $loser = $this->item($dom1, 'São', 'Loser', 10);
        // The 0.4.1 unique index is case and accent sensitive on PostgreSQL only.
        $collided = true;
        try {
            $this->item($dom1, 'SAO', 'Winner', 30);
        } catch (\dml_write_exception $e) {
            $collided = false;
        }
        $this->assertCount($collided ? 5 : 4, $DB->get_records('local_dictionaries_item'));

        // The savepoint refuses to run unless the stored version is below the step's.
        set_config('version', 2026042000, 'local_dictionaries');
        $this->expectOutputRegex($collided ? "/merged code 'São' \(item \d+\) into 'SAO'/" : '/^$/');
        $this->assertTrue(xmldb_local_dictionaries_upgrade(2026042000));
        $this->assertDebuggingNotCalled();
        $this->assertEquals(2026042001, get_config('local_dictionaries', 'version'));

        // Schema: no difference to install.xml for this plugin's tables.
        $file = new \xmldb_file($CFG->dirroot . '/local/dictionaries/db/install.xml');
        $file->loadXMLStructure();
        $this->assertTrue($file->isLoaded());
        $errors = $dbman->check_database_schema($file->getStructure(), ['extratables' => false]);
        $this->assertSame([], $errors);

        // Data: every key matches the runtime functions.
        $items = $DB->get_records('local_dictionaries_item');
        $this->assertCount(4, $items);
        foreach ($items as $item) {
            $this->assertNotSame('', $item->codekey);
            $this->assertNotSame('', $item->searchkey);
            $this->assertSame(local\keys::code_key(local\keys::clean_code($item->code)), $item->codekey);
            $this->assertSame(local\keys::search_key($item->label), $item->searchkey);
        }
        $this->assertTrue($DB->record_exists('local_dictionaries_item', ['id' => $other, 'domainid' => $dom2]));
        $this->assertSame(!$collided, $DB->record_exists('local_dictionaries_item', ['id' => $loser]));
        $this->assertSame(
            $collided ? 1 : 0,
            (int) $DB->get_field('local_dictionaries_domain', 'revision', ['id' => $dom1])
        );
        $this->assertSame(0, (int) $DB->get_field('local_dictionaries_domain', 'revision', ['id' => $dom2]));

        // The runtime reads the migrated rows through the case and accent folded key.
        $this->assertSame($collided ? 'Winner' : 'Loser', resolver::resolve('dom_a', 'sAO'));
        $this->assertSame('São Paulo', resolver::resolve('dom_a', 'sp'));
        $this->assertSame('Other domain', resolver::resolve('dom_b', 'SAO'));
    }
}
