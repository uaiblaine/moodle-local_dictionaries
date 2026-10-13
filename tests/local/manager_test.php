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

namespace local_dictionaries\local;

use local_dictionaries\api;
use local_dictionaries\resolver;

/**
 * Tests for the manager's domain and item writes.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\local\manager
 * @covers     \local_dictionaries\local\keys
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {
    /**
     * The plugin generator.
     *
     * @return \local_dictionaries_generator
     */
    private function generator(): \local_dictionaries_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
    }

    /**
     * An item upsert updates the label that resolution returns.
     */
    public function test_upsert_items_updates_resolved_label(): void {
        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $first = $manager->upsert_items('diretoria', [['code' => '16', 'label' => 'Sao Paulo']]);
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'ignored' => 0], $first);
        $this->assertSame('Sao Paulo', resolver::resolve('diretoria', '16'));

        $second = $manager->upsert_items('diretoria', [['code' => '16', 'label' => 'Sao Paulo - Capital']]);
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'ignored' => 0], $second);
        $this->assertSame('Sao Paulo - Capital', resolver::resolve('diretoria', '16'));
    }

    /**
     * 'ABC' after 'abc' updates the stored item on every database family instead of inserting.
     */
    public function test_upsert_items_matches_codes_case_insensitively(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $domain = $manager->upsert_domain('codes', 'Codes');

        $manager->upsert_items('codes', [['code' => 'abc', 'label' => 'Lower']]);
        $counts = $manager->upsert_items('codes', [['code' => 'ABC', 'label' => 'Upper']]);

        $this->assertSame(['inserted' => 0, 'updated' => 1, 'ignored' => 0], $counts);
        $this->assertSame(1, $DB->count_records('local_dictionaries_item', ['domainid' => $domain->id]));
        $row = $DB->get_record('local_dictionaries_item', ['domainid' => $domain->id]);
        $this->assertSame('ABC', $row->code);
        $this->assertSame('abc', $row->codekey);
        $this->assertSame('Upper', resolver::resolve('codes', 'ABC'));
        $this->assertSame('Upper', resolver::resolve('codes', 'abc'));
    }

    /**
     * A code differing from a stored one only by accents is the same item on every database
     * family: the update keeps the spelling of the last write.
     */
    public function test_upsert_items_accent_variant_updates_the_same_item(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('words', 'Words');
        $manager->upsert_items('words', [['code' => 'são', 'label' => 'Saint']]);

        $counts = $manager->upsert_items('words', [['code' => 'sao', 'label' => 'Plain']]);

        $this->assertSame(['inserted' => 0, 'updated' => 1, 'ignored' => 0], $counts);
        $this->assertSame(1, $DB->count_records('local_dictionaries_item'));
        $row = $DB->get_record('local_dictionaries_item', []);
        $this->assertSame('sao', $row->code);
        $this->assertSame('sao', $row->codekey);
        $this->assertSame('Plain', resolver::resolve('words', 'SÃO'));

        // Two spellings in one batch fold to one item.
        $counts = $manager->upsert_items('words', [
            ['code' => 'Ação', 'label' => 'First'],
            ['code' => 'acao', 'label' => 'Second'],
        ]);
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'ignored' => 1], $counts);
    }

    /**
     * The key derivation folds case and accents but keeps characters without a decomposition.
     */
    public function test_code_key_folds_case_and_accents(): void {
        $this->assertSame('sao paulo', keys::code_key('São PAULO'));
        $this->assertSame('acao', keys::code_key('AÇÃO'));
        $this->assertSame('жук', keys::code_key('Жук'));
    }

    /**
     * Two spellings of one code in one batch: the last one wins and the first counts as ignored.
     */
    public function test_upsert_items_folds_duplicates_in_one_batch(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('codes', 'Codes');

        $counts = $manager->upsert_items('codes', [
            ['code' => 'x1', 'label' => 'First'],
            ['code' => 'X1', 'label' => 'Second'],
        ]);

        $this->assertSame(['inserted' => 1, 'updated' => 0, 'ignored' => 1], $counts);
        $this->assertSame(1, $DB->count_records('local_dictionaries_item'));
        $this->assertSame('Second', resolver::resolve('codes', 'x1'));
    }

    /**
     * The label is folded into the search key, and a write bumps the domain revision.
     */
    public function test_upsert_items_writes_search_key_and_bumps_revision(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $domain = $manager->upsert_domain('cities', 'Cities');
        $this->assertSame(0, (int)$domain->revision);

        $manager->upsert_items('cities', [['code' => '3550308', 'label' => 'São Paulo']]);
        $this->assertSame('sao paulo', $DB->get_field('local_dictionaries_item', 'searchkey', ['codekey' => '3550308']));
        $this->assertSame(1, (int)$DB->get_field('local_dictionaries_domain', 'revision', ['id' => $domain->id]));

        // An unchanged batch writes nothing and leaves the revision alone.
        $counts = $manager->upsert_items('cities', [['code' => '3550308', 'label' => 'São Paulo']]);
        $this->assertSame(['inserted' => 0, 'updated' => 0, 'ignored' => 1], $counts);
        $this->assertSame(1, (int)$DB->get_field('local_dictionaries_domain', 'revision', ['id' => $domain->id]));
    }

    /**
     * Optional keys left out keep their stored values; given ones change them.
     */
    public function test_upsert_items_optional_keys_keep_or_change(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('codes', 'Codes');
        $manager->upsert_items('codes', [['code' => 'a', 'label' => 'A', 'sortorder' => 5, 'active' => false]]);

        $row = $DB->get_record('local_dictionaries_item', ['codekey' => 'a']);
        $this->assertSame(5, (int)$row->sortorder);
        $this->assertSame(0, (int)$row->active);

        $counts = $manager->upsert_items('codes', [['code' => 'a', 'label' => 'A']]);
        $this->assertSame(['inserted' => 0, 'updated' => 0, 'ignored' => 1], $counts);
        $row = $DB->get_record('local_dictionaries_item', ['codekey' => 'a']);
        $this->assertSame(5, (int)$row->sortorder);
        $this->assertSame(0, (int)$row->active);

        $counts = $manager->upsert_items('codes', [['code' => 'a', 'label' => 'A', 'active' => true]]);
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'ignored' => 0], $counts);
        $this->assertSame(1, (int)$DB->get_field('local_dictionaries_item', 'active', ['codekey' => 'a']));
    }

    /**
     * Parents resolve in another domain, or in the same batch, and an empty parent code clears them.
     */
    public function test_upsert_items_sets_and_clears_parents(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('uf', 'States');
        $manager->upsert_domain('municipio', 'Municipalities');
        $manager->upsert_items('uf', [['code' => 'SP', 'label' => 'São Paulo']]);
        $spid = (int)$DB->get_field('local_dictionaries_item', 'id', ['codekey' => 'sp']);

        $manager->upsert_items('municipio', [['code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'sp']], 'uf');
        $this->assertSame($spid, (int)$DB->get_field('local_dictionaries_item', 'parentid', ['codekey' => '3550308']));

        // A parent written earlier in the same batch, same domain.
        $manager->upsert_items('uf', [
            ['code' => 'BR', 'label' => 'Brasil', 'parentcode' => ''],
            ['code' => 'RJ', 'label' => 'Rio de Janeiro', 'parentcode' => 'BR'],
        ]);
        $brid = (int)$DB->get_field('local_dictionaries_item', 'id', ['codekey' => 'br']);
        $this->assertSame($brid, (int)$DB->get_field('local_dictionaries_item', 'parentid', ['codekey' => 'rj']));

        $counts = $manager->upsert_items('uf', [['code' => 'RJ', 'label' => 'Rio de Janeiro', 'parentcode' => '']]);
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'ignored' => 0], $counts);
        $this->assertNull($DB->get_field('local_dictionaries_item', 'parentid', ['codekey' => 'rj']));
    }

    /**
     * An unknown parent code refuses the whole batch before anything is written.
     */
    public function test_upsert_items_refuses_unknown_parent(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('uf', 'States');

        try {
            $manager->upsert_items('uf', [
                ['code' => 'SP', 'label' => 'São Paulo'],
                ['code' => 'RJ', 'label' => 'Rio de Janeiro', 'parentcode' => 'nowhere'],
            ]);
            $this->fail('An unknown parent must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorunknownparent', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dictionaries_item'));
    }

    /**
     * An item cannot be its own parent.
     */
    public function test_upsert_items_refuses_self_parent(): void {
        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('uf', 'States');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorparentself', 'local_dictionaries', 'SP'));
        $manager->upsert_items('uf', [['code' => 'SP', 'label' => 'São Paulo', 'parentcode' => 'sp']]);
    }

    /**
     * A code longer than the column is refused, never cut.
     */
    public function test_upsert_items_refuses_overlong_code(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('codes', 'Codes');

        try {
            $manager->upsert_items('codes', [
                ['code' => str_repeat('a', 255), 'label' => 'Fits'],
                ['code' => str_repeat('b', 256), 'label' => 'Too long'],
            ]);
            $this->fail('An over-long code must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcodetoolong', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dictionaries_item'));
    }

    /**
     * Unknown domains surface the localised error message, not a generic one.
     */
    public function test_upsert_items_unknown_domain_throws_with_message(): void {
        $this->resetAfterTest();
        $manager = new manager();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorunknowndomain', 'local_dictionaries', 'missing_domain'));
        $manager->upsert_items('missing_domain', [['code' => '1', 'label' => 'X']]);
    }

    /**
     * Chunked upsert processes every item across chunk boundaries and merges counts.
     */
    public function test_upsert_items_chunked_processes_all_items(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('diretoria', 'Diretoria');

        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = ['code' => 'c' . $i, 'label' => 'Label ' . $i];
        }

        $counts = $manager->upsert_items_chunked('diretoria', $items, 2);
        $this->assertSame(['inserted' => 5, 'updated' => 0, 'ignored' => 0], $counts);
        $this->assertSame(5, $DB->count_records('local_dictionaries_item'));
        $this->assertSame('Label 4', resolver::resolve('diretoria', 'c4'));

        $counts = $manager->upsert_items_chunked('diretoria', $items, 2);
        $this->assertSame(['inserted' => 0, 'updated' => 0, 'ignored' => 5], $counts);
    }

    /**
     * Chunked upsert checks every parent before the first chunk is written.
     */
    public function test_upsert_items_chunked_checks_parents_first(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('uf', 'States');

        try {
            $manager->upsert_items_chunked('uf', [
                ['code' => 'A', 'label' => 'A'],
                ['code' => 'B', 'label' => 'B'],
                ['code' => 'C', 'label' => 'C', 'parentcode' => 'nowhere'],
            ], 1);
            $this->fail('An unknown parent must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorunknownparent', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dictionaries_item'));
    }

    /**
     * Chunked upsert writes a parent before its child even when the file lists the child first.
     */
    public function test_upsert_items_chunked_orders_parents_before_children(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('org', 'Org');

        // The child, and a grandchild listed before its own parent, sit in earlier chunks than their parents.
        $counts = $manager->upsert_items_chunked('org', [
            ['code' => 'leaf', 'label' => 'Leaf', 'parentcode' => 'mid'],
            ['code' => 'other', 'label' => 'Other'],
            ['code' => 'mid', 'label' => 'Mid', 'parentcode' => 'root'],
            ['code' => 'filler', 'label' => 'Filler'],
            ['code' => 'root', 'label' => 'Root'],
        ], 2);

        $this->assertSame(['inserted' => 5, 'updated' => 0, 'ignored' => 0], $counts);
        $ids = $DB->get_records_menu('local_dictionaries_item', [], '', 'codekey, id');
        $parents = $DB->get_records_menu('local_dictionaries_item', [], '', 'codekey, parentid');
        $this->assertEquals($ids['mid'], $parents['leaf']);
        $this->assertEquals($ids['root'], $parents['mid']);
        $this->assertNull($parents['root']);
    }

    /**
     * Chunked upsert refuses a parent cycle before writing anything.
     */
    public function test_upsert_items_chunked_refuses_parent_cycle_before_writing(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('org', 'Org');

        $sink = $this->redirectEvents();
        try {
            $manager->upsert_items_chunked('org', [
                ['code' => 'free', 'label' => 'Free'],
                ['code' => 'A', 'label' => 'A', 'parentcode' => 'B'],
                ['code' => 'B', 'label' => 'B', 'parentcode' => 'A'],
            ], 1);
            $this->fail('A parent cycle must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorparentcycle', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dictionaries_item'));
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * A batch where two items name each other as parent is refused, with no row and no event.
     */
    public function test_upsert_items_refuses_parent_cycle_in_one_batch(): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('org', 'Org');

        $sink = $this->redirectEvents();
        try {
            $manager->upsert_items('org', [
                ['code' => 'A', 'label' => 'A', 'parentcode' => 'B'],
                ['code' => 'B', 'label' => 'B', 'parentcode' => 'A'],
            ]);
            $this->fail('A parent cycle must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorparentcycle', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dictionaries_item'));
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * Data provider for the two write paths.
     *
     * @return array
     */
    public static function write_path_provider(): array {
        return [
            'upsert_items' => ['upsert_items'],
            'upsert_items_chunked' => ['upsert_items_chunked'],
        ];
    }

    /**
     * A batch that closes a loop through rows already stored is refused on both write paths.
     *
     * @dataProvider write_path_provider
     * @param string $method
     */
    public function test_parent_cycle_through_stored_rows_is_refused(string $method): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('org', 'Org');
        $manager->upsert_items('org', [
            ['code' => 'B', 'label' => 'B'],
            ['code' => 'A', 'label' => 'A', 'parentcode' => 'B'],
            ['code' => 'C', 'label' => 'C', 'parentcode' => 'A'],
        ]);
        $before = $DB->get_records_menu('local_dictionaries_item', [], 'id', 'codekey, parentid');

        $sink = $this->redirectEvents();
        // B -> C closes C -> A -> B -> C two levels away from the item the batch names.
        try {
            $manager->$method('org', [['code' => 'B', 'label' => 'B', 'parentcode' => 'C']]);
            $this->fail('A parent cycle must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorparentcycle', $e->errorcode);
        }
        $this->assertEquals($before, $DB->get_records_menu('local_dictionaries_item', [], 'id', 'codekey, parentid'));
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * Re-parenting that does not close a loop still works, including moves under a former child.
     *
     * @dataProvider write_path_provider
     * @param string $method
     */
    public function test_reparenting_without_a_loop_is_written(string $method): void {
        global $DB;

        $this->resetAfterTest();
        $manager = new manager();
        $manager->upsert_domain('org', 'Org');
        $manager->upsert_items('org', [
            ['code' => 'A', 'label' => 'A'],
            ['code' => 'B', 'label' => 'B', 'parentcode' => 'A'],
            ['code' => 'C', 'label' => 'C', 'parentcode' => 'B'],
        ]);

        // Swap roles in one batch: B leaves A and C leaves B, so no loop survives the write.
        $manager->$method('org', [
            ['code' => 'C', 'label' => 'C', 'parentcode' => 'A'],
            ['code' => 'B', 'label' => 'B', 'parentcode' => 'C'],
            ['code' => 'A', 'label' => 'A', 'parentcode' => ''],
        ]);
        $ids = $DB->get_records_menu('local_dictionaries_item', [], '', 'codekey, id');
        $parents = $DB->get_records_menu('local_dictionaries_item', [], '', 'codekey, parentid');
        $this->assertNull($parents['a']);
        $this->assertEquals($ids['a'], $parents['c']);
        $this->assertEquals($ids['c'], $parents['b']);
    }

    /**
     * Domain upsert stores description and visibility, and keeps them when they are not given.
     */
    public function test_upsert_domain_description_and_visibility(): void {
        $this->resetAfterTest();
        $manager = new manager();

        $domain = $manager->upsert_domain('uf', 'States');
        $this->assertSame(api::VISIBILITY_RESTRICTED, (int)$domain->visibility);
        $this->assertNull($domain->description);

        $domain = $manager->upsert_domain('uf', 'States', 'Brazilian states', api::VISIBILITY_LOGGEDIN);
        $this->assertSame('Brazilian states', $domain->description);
        $this->assertSame(api::VISIBILITY_LOGGEDIN, (int)$domain->visibility);

        $domain = $manager->upsert_domain('uf', 'States renamed');
        $this->assertSame('Brazilian states', $domain->description);
        $this->assertSame(api::VISIBILITY_LOGGEDIN, (int)$domain->visibility);

        $domain = $manager->upsert_domain('uf', 'States renamed', '');
        $this->assertNull($domain->description);
    }

    /**
     * Domain upsert refuses an invalid or over-long shortname and an unknown visibility.
     */
    public function test_upsert_domain_refuses_invalid_input(): void {
        $this->resetAfterTest();
        $manager = new manager();

        $cases = [
            '' => 'domainrequired',
            'Bad Domain!' => 'domaininvalid',
            str_repeat('a', 101) => 'domaintoolong',
        ];
        foreach ($cases as $name => $code) {
            try {
                $manager->upsert_domain((string)$name, 'Name');
                $this->fail('Shortname "' . $name . '" must be refused.');
            } catch (\moodle_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }

        $this->expectException(\coding_exception::class);
        $manager->upsert_domain('valid', 'Name', null, 7);
    }

    /**
     * The domain list carries item counts.
     */
    public function test_list_domains_counts_items(): void {
        $this->resetAfterTest();
        $this->generator()->create_domain(['shortname' => 'empty']);
        $this->generator()->create_domain(['shortname' => 'full']);
        $this->generator()->create_item(['domain' => 'full', 'code' => '1']);
        $this->generator()->create_item(['domain' => 'full', 'code' => '2']);

        $counts = [];
        foreach ((new manager())->list_domains() as $domain) {
            $counts[$domain->shortname] = (int)$domain->itemcount;
        }
        $this->assertSame(['empty' => 0, 'full' => 2], $counts);
    }
}
