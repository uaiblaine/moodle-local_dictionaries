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

use local_dictionaries\local\manager;

/**
 * Tests for the plugin's upgrade steps.
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
    /** @var int The step that recomposes stored code keys, in this branch's version namespace. */
    private const REKEY_STEP = 2026042003;

    /**
     * Upgrading from before the step rewrites a code key stored under the old derivation.
     */
    public function test_rekey_step_rewrites_old_keys(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/dictionaries/db/upgrade.php');

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('hangul', 'Hangul');
        $manager->upsert_items('hangul', [['code' => '한국', 'label' => 'Korea']]);
        $oldkey = \Normalizer::normalize('한국', \Normalizer::FORM_D);
        $DB->set_field('local_dictionaries_item', 'codekey', $oldkey, ['code' => '한국']);
        set_config('version', self::REKEY_STEP - 1, 'local_dictionaries');

        xmldb_local_dictionaries_upgrade(self::REKEY_STEP - 1);

        $this->assertSame('한국', $DB->get_field('local_dictionaries_item', 'codekey', ['code' => '한국']));
        $this->assertEquals(self::REKEY_STEP, get_config('local_dictionaries', 'version'));
    }
}
