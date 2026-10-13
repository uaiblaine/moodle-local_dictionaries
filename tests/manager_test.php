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
 * Tests for local_dictionaries manager and resolver.
 *
 * @package    local_dictionaries
 * @covers     \local_dictionaries\local\manager
 * @covers     \local_dictionaries\resolver
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {
    /** @var string Test city label. */
    private const CITY_LABEL = 'Sao Paulo';
    /**
     * Ensure item upsert updates cache-visible label resolution.
     */
    public function test_upsert_items_updates_resolved_label(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        if (
            !$DB->get_manager()->table_exists(new \xmldb_table('local_dictionaries_domain')) ||
            !$DB->get_manager()->table_exists(new \xmldb_table('local_dictionaries_item'))
        ) {
            $this->markTestSkipped('local_dictionaries tables are not available.');
        }

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $first = $manager->upsert_items('diretoria', [[
            'code' => '16',
            'label' => self::CITY_LABEL,
        ]]);
        $this->assertSame(1, $first['inserted']);
        $this->assertSame(0, $first['updated']);

        $this->assertSame('Sao Paulo', \local_dictionaries\resolver::resolve('diretoria', '16'));

        $second = $manager->upsert_items('diretoria', [[
            'code' => '16',
            'label' => 'Sao Paulo - Capital',
        ]]);
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(1, $second['updated']);

        $this->assertSame('Sao Paulo - Capital', \local_dictionaries\resolver::resolve('diretoria', '16'));
    }

    /**
     * Ensure CSV parser accepts optional header and returns code-label rows.
     */
    public function test_parse_csv_content_with_header(): void {
        $manager = new \local_dictionaries\local\manager();
        $items = $manager->parse_csv_content("code,label\n16,Sao Paulo\n17,Rio");

        $this->assertCount(2, $items);
        $this->assertSame('16', $items[0]['code']);
        $this->assertSame('Sao Paulo', $items[0]['label']);
        $this->assertSame('17', $items[1]['code']);
        $this->assertSame('Rio', $items[1]['label']);
    }

    /**
     * Unknown domains must surface the localised error message, not a generic one.
     */
    public function test_upsert_items_unknown_domain_throws_with_message(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        if (!$DB->get_manager()->table_exists(new \xmldb_table('local_dictionaries_domain'))) {
            $this->markTestSkipped('local_dictionaries tables are not available.');
        }

        $manager = new \local_dictionaries\local\manager();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(
            get_string('errorunknowndomain', 'local_dictionaries', 'missing_domain')
        );
        $manager->upsert_items('missing_domain', [['code' => '1', 'label' => 'X']]);
    }

    /**
     * Chunked upsert must process every item across chunk boundaries and merge counts.
     */
    public function test_upsert_items_chunked_processes_all_items(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        if (!$DB->get_manager()->table_exists(new \xmldb_table('local_dictionaries_domain'))) {
            $this->markTestSkipped('local_dictionaries tables are not available.');
        }

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = ['code' => 'c' . $i, 'label' => 'Label ' . $i];
        }

        $counts = $manager->upsert_items_chunked('diretoria', $items, 2);

        $this->assertSame(5, $counts['inserted']);
        $this->assertSame(0, $counts['updated']);
        $this->assertSame(0, $counts['ignored']);
        $this->assertSame(5, $DB->count_records('local_dictionaries_item'));
        $this->assertSame('Label 4', \local_dictionaries\resolver::resolve('diretoria', 'c4'));

        $counts = $manager->upsert_items_chunked('diretoria', $items, 2);
        $this->assertSame(0, $counts['inserted']);
        $this->assertSame(5, $counts['ignored']);
    }
}
