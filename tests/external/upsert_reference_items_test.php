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

namespace local_dictionaries\external;

use core_external\external_api;

/**
 * Tests for upsert_reference_items external function.
 *
 * @package    local_dictionaries
 * @covers     \local_dictionaries\external\upsert_reference_items
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upsert_reference_items_test extends \advanced_testcase {
    /**
     * Skip when plugin tables are not present.
     */
    private function require_tables(): void {
        global $DB;
        $dbman = $DB->get_manager();
        if (
            !$dbman->table_exists(new \xmldb_table('local_dictionaries_domain')) ||
            !$dbman->table_exists(new \xmldb_table('local_dictionaries_item'))
        ) {
            $this->markTestSkipped('local_dictionaries tables are not available.');
        }
    }

    /**
     * Inserts new items and reports counts.
     */
    public function test_execute_inserts_new_items(): void {
        $this->resetAfterTest();
        $this->require_tables();
        $this->setAdminUser();

        (new \local_dictionaries\local\manager())->upsert_domain('diretoria', 'Diretoria');

        $result = upsert_reference_items::execute('diretoria', [
            ['code' => '01', 'label' => 'Sao Paulo'],
            ['code' => '02', 'label' => 'Rio'],
        ]);
        $result = external_api::clean_returnvalue(upsert_reference_items::execute_returns(), $result);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['ignored']);
    }

    /**
     * Throws when batch size exceeds the configured maximum.
     */
    public function test_execute_rejects_oversized_batch(): void {
        $this->resetAfterTest();
        $this->require_tables();
        $this->setAdminUser();

        $items = array_fill(
            0,
            \local_dictionaries\local\manager::MAX_BATCH_SIZE + 1,
            ['code' => 'x', 'label' => 'y']
        );

        $this->expectException(\invalid_parameter_exception::class);
        upsert_reference_items::execute('diretoria', $items);
    }

    /**
     * Unknown domains must raise the localised error, not a generic parameter error.
     */
    public function test_execute_unknown_domain_throws_with_message(): void {
        $this->resetAfterTest();
        $this->require_tables();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(
            get_string('errorunknowndomain', 'local_dictionaries', 'missing_domain')
        );
        upsert_reference_items::execute('missing_domain', [['code' => '01', 'label' => 'X']]);
    }

    /**
     * Requires the manage capability.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        $this->require_tables();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        upsert_reference_items::execute('diretoria', [['code' => '01', 'label' => 'x']]);
    }
}
