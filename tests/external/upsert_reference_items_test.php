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
 * Tests for the upsert_reference_items web service.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\external\upsert_reference_items
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upsert_reference_items_test extends \advanced_testcase {
    /**
     * Inserts new items, with optional keys, and reports counts.
     */
    public function test_execute_inserts_new_items(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'uf']);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'São Paulo']);
        $generator->create_domain(['shortname' => 'cities']);

        $result = upsert_reference_items::execute('cities', [
            ['code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'SP', 'sortorder' => 3],
            ['code' => '3509502', 'label' => 'Campinas', 'active' => false],
        ], 'uf');
        $result = external_api::clean_returnvalue(upsert_reference_items::execute_returns(), $result);

        $this->assertSame(['inserted' => 2, 'updated' => 0, 'ignored' => 0], $result);
        $row = $DB->get_record('local_dictionaries_item', ['codekey' => '3550308']);
        $this->assertSame(3, (int)$row->sortorder);
        $this->assertSame(
            (int)$DB->get_field('local_dictionaries_item', 'id', ['codekey' => 'sp']),
            (int)$row->parentid
        );
        $this->assertSame(0, (int)$DB->get_field('local_dictionaries_item', 'active', ['codekey' => '3509502']));
    }

    /**
     * Throws when the batch size exceeds the maximum.
     */
    public function test_execute_rejects_oversized_batch(): void {
        $this->resetAfterTest();
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
     * Unknown domains raise the localised error, not a generic parameter error.
     */
    public function test_execute_unknown_domain_throws_with_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorunknowndomain', 'local_dictionaries', 'missing_domain'));
        upsert_reference_items::execute('missing_domain', [['code' => '01', 'label' => 'X']]);
    }

    /**
     * Requires the manage capability.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        upsert_reference_items::execute('diretoria', [['code' => '01', 'label' => 'x']]);
    }

    /**
     * The guest user is refused before the capability check.
     */
    public function test_execute_refuses_guest(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('noguest', 'error'));
        upsert_reference_items::execute('diretoria', [['code' => '01', 'label' => 'x']]);
    }
}
