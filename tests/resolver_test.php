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
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\resolver
 * @covers     \local_dictionaries\local\domains
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver_test extends \advanced_testcase {
    /**
     * The plugin generator.
     *
     * @return \local_dictionaries_generator
     */
    private function generator(): \local_dictionaries_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
    }

    /**
     * Resolve returns null for an unknown code.
     */
    public function test_resolve_returns_null_for_unknown_code(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'diretoria']);

        $this->assertNull(resolver::resolve('diretoria', 'missing'));
    }

    /**
     * resolve_bulk returns the labels of known codes only.
     */
    public function test_resolve_bulk_returns_resolved_labels(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'diretoria']);
        $this->generator()->create_item(['domain' => 'diretoria', 'code' => '01', 'label' => 'Sao Paulo']);
        $this->generator()->create_item(['domain' => 'diretoria', 'code' => '02', 'label' => 'Rio']);

        $result = resolver::resolve_bulk('diretoria', ['01', '02', '99']);
        $this->assertSame(['01' => 'Sao Paulo', '02' => 'Rio'], $result);
    }

    /**
     * Codes are case-insensitive, and results are keyed by the caller's spelling.
     */
    public function test_resolve_bulk_is_case_insensitive_and_keyed_by_caller_spelling(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'iso639']);
        $this->generator()->create_item(['domain' => 'iso639', 'code' => 'pt', 'label' => 'Portuguese']);

        $this->assertSame('Portuguese', resolver::resolve('iso639', 'PT'));
        $this->assertSame(
            ['PT' => 'Portuguese', 'pt' => 'Portuguese', 'Pt' => 'Portuguese'],
            resolver::resolve_bulk('iso639', ['PT', 'pt', ' Pt '])
        );
    }

    /**
     * A write invalidates labels already in the cache: the domain revision is part of the key.
     */
    public function test_resolve_sees_a_label_written_after_it_was_cached(): void {
        global $DB;

        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'diretoria']);
        $this->generator()->create_item(['domain' => 'diretoria', 'code' => '16', 'label' => 'Sao Paulo']);
        $this->assertSame('Sao Paulo', resolver::resolve('diretoria', '16'));

        // Control: the label is now served from the cache, not from the table.
        $DB->set_field('local_dictionaries_item', 'label', 'Changed behind the cache', ['codekey' => '16']);
        $this->assertSame('Sao Paulo', resolver::resolve('diretoria', '16'));

        $this->generator()->create_item(['domain' => 'diretoria', 'code' => '16', 'label' => 'Sao Paulo - Capital']);
        $this->assertSame('Sao Paulo - Capital', resolver::resolve('diretoria', '16'));
    }

    /**
     * Codes are equal ignoring case and accents, on every database family.
     *
     * The code key folds both, so the lookup does not depend on the collation.
     */
    public function test_accent_variant_is_the_same_code(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'words']);
        $this->generator()->create_item(['domain' => 'words', 'code' => 'são', 'label' => 'Saint']);

        $this->assertSame(['sao' => 'Saint'], resolver::resolve_bulk('words', ['sao']));
        $this->assertSame(['SÃO' => 'Saint'], resolver::resolve_bulk('words', ['SÃO']));
        $this->assertSame('Saint', resolver::resolve('words', 'Sao'));
        // Control: a different code does not resolve.
        $this->assertSame([], resolver::resolve_bulk('words', ['sa']));
    }

    /**
     * Inactive items are no longer offered by search but still resolve.
     */
    public function test_inactive_items_still_resolve(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'uf']);
        $this->generator()->create_item(['domain' => 'uf', 'code' => 'GB', 'label' => 'Guanabara', 'active' => 0]);

        $this->assertSame('Guanabara', resolver::resolve('uf', 'GB'));
    }

    /**
     * Empty input, an unknown domain or an invalid shortname give an empty result.
     */
    public function test_resolve_bulk_empty_or_unknown(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'diretoria']);
        $this->generator()->create_item(['domain' => 'diretoria', 'code' => '01', 'label' => 'Sao Paulo']);

        $this->assertSame([], resolver::resolve_bulk('diretoria', []));
        $this->assertSame([], resolver::resolve_bulk('diretoria', ['', '  ']));
        $this->assertSame([], resolver::resolve_bulk('unknowndomain', ['01']));
        $this->assertSame([], resolver::resolve_bulk('Bad Domain!', ['01']));
        // Control: the same code resolves in the right domain.
        $this->assertSame(['01' => 'Sao Paulo'], resolver::resolve_bulk('DIRETORIA', ['01']));
    }

    /**
     * A code whose key differs from a stored one is not resolved to it, even where the collation folds the two.
     *
     * MariaDB (utf8mb4_unicode_ci) treats 'ß' and 'ss' as equal in the IN list, so it returns the
     * 'ss' row for the key 'ß'; PostgreSQL does not. The result must be the same on both.
     */
    public function test_resolve_bulk_ignores_rows_the_collation_folds_onto_another_key(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'folds']);
        $this->generator()->create_item(['domain' => 'folds', 'code' => 'ss', 'label' => 'Double s']);

        $this->assertSame([], resolver::resolve_bulk('folds', ['ß']));
        $this->assertNull(resolver::resolve('folds', 'ß'));
        // Control: the stored spelling still resolves.
        $this->assertSame(['ss' => 'Double s'], resolver::resolve_bulk('folds', ['ss']));
    }

    /**
     * domain_exists detects created domains and rejects invalid shortnames.
     */
    public function test_domain_exists(): void {
        $this->resetAfterTest();

        $this->assertFalse(resolver::domain_exists('diretoria'));
        $this->generator()->create_domain(['shortname' => 'diretoria']);

        $this->assertTrue(resolver::domain_exists('diretoria'));
        $this->assertFalse(resolver::domain_exists('INVALID name'));
    }

    /**
     * The pattern constant is the public contract consumers read.
     */
    public function test_domain_pattern_constant(): void {
        $this->assertSame(1, preg_match(resolver::DOMAIN_PATTERN, 'iso_639_1'));
        $this->assertSame(0, preg_match(resolver::DOMAIN_PATTERN, 'Iso-639'));
    }
}
