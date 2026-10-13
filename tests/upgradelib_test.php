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
 * Tests for the helpers of the 0.4.1 to 0.5.0 upgrade step.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     ::local_dictionaries_backfill_item_keys
 * @covers     ::local_dictionaries_merge_codekey_collisions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgradelib_test extends \advanced_testcase {
    /** @var bool Whether the unique codekey index was dropped to stage a 0.4.1 table. */
    private bool $indexdropped = false;

    /** @var int Counter that gives every domain of a test its own shortname. */
    private int $domaincount = 0;

    /**
     * The index of the (domainid, codekey) pair, as install.xml declares it.
     *
     * @return \xmldb_index
     */
    private function codekey_index(): \xmldb_index {
        return new \xmldb_index('domainid_codekey_uix', XMLDB_INDEX_UNIQUE, ['domainid', 'codekey']);
    }

    /**
     * Put the unique index back, whatever the test left in the table.
     */
    protected function tearDown(): void {
        global $DB;
        if ($this->indexdropped) {
            $DB->delete_records('local_dictionaries_item');
            $DB->get_manager()->add_index(new \xmldb_table('local_dictionaries_item'), $this->codekey_index());
        }
        parent::tearDown();
    }

    /**
     * Load the helpers, stage a table without the unique codekey index (as 0.4.1 left it) and insert a domain.
     *
     * @return int The domain id.
     */
    private function domain(): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/dictionaries/db/upgradelib.php');
        $this->resetAfterTest();
        if (!$this->indexdropped) {
            $table = new \xmldb_table('local_dictionaries_item');
            $DB->get_manager()->drop_index($table, $this->codekey_index());
            $this->indexdropped = true;
        }
        return (int) $DB->insert_record('local_dictionaries_domain', (object) [
            'shortname' => 'dom' . ++$this->domaincount,
            'name' => 'Domain',
            'visibility' => 0,
            'revision' => 3,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /**
     * Insert an item as a 0.4.1 site holds it: keys empty.
     *
     * @param int $domainid
     * @param string $code
     * @param string $label
     * @param int $timemodified
     * @return int The item id.
     */
    private function legacy_item(int $domainid, string $code, string $label, int $timemodified = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_dictionaries_item', (object) [
            'domainid' => $domainid,
            'code' => $code,
            'codekey' => '',
            'label' => $label,
            'searchkey' => '',
            'sortorder' => 0,
            'active' => 1,
            'timecreated' => 1,
            'timemodified' => $timemodified,
        ]);
    }

    /**
     * The backfill derives both keys with the runtime functions, across several chunks.
     */
    public function test_backfill_fills_both_keys_in_chunks(): void {
        global $DB;
        $domainid = $this->domain();
        $a = $this->legacy_item($domainid, 'SP', 'São Paulo');
        $b = $this->legacy_item($domainid, 'Ação', 'Ação  Imediata');
        $c = $this->legacy_item($domainid, 'x1', 'Plain');

        $this->assertSame(3, local_dictionaries_backfill_item_keys(2));

        $this->assertSame('sp', $DB->get_field('local_dictionaries_item', 'codekey', ['id' => $a]));
        $this->assertSame('sao paulo', $DB->get_field('local_dictionaries_item', 'searchkey', ['id' => $a]));
        $this->assertSame('acao', $DB->get_field('local_dictionaries_item', 'codekey', ['id' => $b]));
        $this->assertSame('acao imediata', $DB->get_field('local_dictionaries_item', 'searchkey', ['id' => $b]));
        $this->assertSame('x1', $DB->get_field('local_dictionaries_item', 'codekey', ['id' => $c]));
        // Rerunning finds nothing left to fill.
        $this->assertSame(0, local_dictionaries_backfill_item_keys(2));
    }

    /**
     * Codes equal ignoring case and accents collapse to the most recently modified row.
     */
    public function test_collision_keeps_most_recently_modified(): void {
        global $DB;
        $domainid = $this->domain();
        $old = $this->legacy_item($domainid, 'sao', 'Old', 10);
        $new = $this->legacy_item($domainid, 'São', 'New', 30);
        $mid = $this->legacy_item($domainid, 'SAO', 'Mid', 20);
        $other = $this->legacy_item($domainid, 'rio', 'Rio', 5);
        // A child of a row that will be deleted follows the survivor.
        $DB->set_field('local_dictionaries_item', 'parentid', $old, ['id' => $other]);
        local_dictionaries_backfill_item_keys();

        $messages = local_dictionaries_merge_codekey_collisions();

        $this->assertCount(2, $messages);
        $this->assertEqualsCanonicalizing([$new, $other], array_keys($DB->get_records('local_dictionaries_item')));
        $this->assertSame('New', $DB->get_field('local_dictionaries_item', 'label', ['id' => $new]));
        $this->assertEquals($new, $DB->get_field('local_dictionaries_item', 'parentid', ['id' => $other]));
        $this->assertSame(4, (int) $DB->get_field('local_dictionaries_domain', 'revision', ['id' => $domainid]));
        $this->assertFalse($DB->record_exists('local_dictionaries_item', ['id' => $mid]));
    }

    /**
     * Merge messages escape the stored codes: mtrace() echoes them into the web upgrade page.
     *
     * @return void
     */
    public function test_collision_messages_escape_codes(): void {
        $domainid = $this->domain();
        $this->legacy_item($domainid, '<b>x</b>', 'Old', 10);
        $this->legacy_item($domainid, '<B>X</B>', 'New', 20);
        local_dictionaries_backfill_item_keys();

        $messages = local_dictionaries_merge_codekey_collisions();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $messages[0]);
        $this->assertStringNotContainsString('<b>', $messages[0]);
        $this->assertStringNotContainsString('<B>', $messages[0]);
    }

    /**
     * A tie on timemodified keeps the highest id, and equal codes in different domains are left alone.
     */
    public function test_collision_tie_and_other_domains(): void {
        global $DB;
        $domainid = $this->domain();
        $first = $this->legacy_item($domainid, 'A', 'One', 7);
        $second = $this->legacy_item($domainid, 'a', 'Two', 7);
        $otherdomain = $this->domain();
        $elsewhere = $this->legacy_item($otherdomain, 'A', 'Three', 7);
        local_dictionaries_backfill_item_keys();

        $this->assertCount(1, local_dictionaries_merge_codekey_collisions());

        $this->assertFalse($DB->record_exists('local_dictionaries_item', ['id' => $first]));
        $this->assertTrue($DB->record_exists('local_dictionaries_item', ['id' => $second]));
        $this->assertTrue($DB->record_exists('local_dictionaries_item', ['id' => $elsewhere]));
        $this->assertSame([], local_dictionaries_merge_codekey_collisions());
    }
}
