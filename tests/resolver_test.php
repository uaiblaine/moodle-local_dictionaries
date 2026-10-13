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
 * Tests for the resolver class.
 *
 * @package    local_dictionaries
 * @covers     \local_dictionaries\resolver
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver_test extends \advanced_testcase {
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
     * Resolve returns null for unknown code.
     */
    public function test_resolve_returns_null_for_unknown_code(): void {
        $this->resetAfterTest();
        $this->require_tables();

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $this->assertNull(resolver::resolve('diretoria', 'missing'));
    }

    /**
     * resolve_bulk returns map of resolved labels.
     */
    public function test_resolve_bulk_returns_resolved_labels(): void {
        $this->resetAfterTest();
        $this->require_tables();

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_domain('diretoria', 'Diretoria');
        $manager->upsert_items('diretoria', [
            ['code' => '01', 'label' => 'Sao Paulo'],
            ['code' => '02', 'label' => 'Rio'],
        ]);

        $result = resolver::resolve_bulk('diretoria', ['01', '02', '99']);
        $this->assertSame('Sao Paulo', $result['01']);
        $this->assertSame('Rio', $result['02']);
        $this->assertArrayNotHasKey('99', $result);
    }

    /**
     * Empty input or unknown domain returns empty array.
     */
    public function test_resolve_bulk_empty_or_unknown(): void {
        $this->resetAfterTest();
        $this->require_tables();

        $this->assertSame([], resolver::resolve_bulk('diretoria', []));
        $this->assertSame([], resolver::resolve_bulk('unknowndomain', ['01']));
    }

    /**
     * domain_exists detects created domains.
     */
    public function test_domain_exists(): void {
        $this->resetAfterTest();
        $this->require_tables();

        $this->assertFalse(resolver::domain_exists('diretoria'));

        $manager = new \local_dictionaries\local\manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $this->assertTrue(resolver::domain_exists('diretoria'));
        $this->assertFalse(resolver::domain_exists('INVALID name'));
    }
}
