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
 * Tests for the search API.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\api
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_test extends \advanced_testcase {
    /**
     * Create the 'cities' domain with a few items.
     *
     * @return void
     */
    private function create_cities(): void {
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'uf']);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'São Paulo']);
        $generator->create_item(['domain' => 'uf', 'code' => 'PA', 'label' => 'Pará']);

        $generator->create_domain(['shortname' => 'cities']);
        $items = [
            ['code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'SP'],
            ['code' => '3548708', 'label' => 'São Bernardo do Campo', 'parentcode' => 'SP'],
            ['code' => '3509502', 'label' => 'Campinas', 'parentcode' => 'SP'],
            ['code' => '1501402', 'label' => 'Belém', 'parentcode' => 'PA'],
            ['code' => '1500800', 'label' => 'Ananindeua', 'parentcode' => 'PA'],
            ['code' => '9999999', 'label' => 'Paulo Afonso extinct', 'parentcode' => 'PA', 'active' => 0],
        ];
        foreach ($items as $item) {
            $generator->create_item(['domain' => 'cities', 'parentdomain' => 'uf'] + $item);
        }
    }

    /**
     * Codes of a search result, in order.
     *
     * @param array $result
     * @return array
     */
    private function codes(array $result): array {
        return array_column($result['items'], 'code');
    }

    /**
     * Prefix matches come before substring matches; accents and case do not matter.
     */
    public function test_prefix_before_substring_accent_insensitive(): void {
        $this->resetAfterTest();
        $this->create_cities();

        // Campinas and Sao Bernardo do Campo both contain 'camp'; only the first starts with it.
        $result = api::search('cities', 'CAMP');
        $this->assertSame(['3509502', '3548708'], $this->codes($result));
        $this->assertFalse($result['hasmore']);

        $this->assertSame(['3548708', '3550308'], $this->codes(api::search('cities', 'são')));
        $this->assertSame(['3548708', '3550308'], $this->codes(api::search('cities', 'sao')));
        $this->assertSame(['1501402'], $this->codes(api::search('cities', 'BELEM')));
    }

    /**
     * Inactive items are not offered, though they would match.
     */
    public function test_inactive_items_are_not_offered(): void {
        $this->resetAfterTest();
        $this->create_cities();

        $this->assertSame(['3550308'], $this->codes(api::search('cities', 'paulo')));
    }

    /**
     * A query equal to a code finds that item first.
     */
    public function test_query_equal_to_a_code_matches(): void {
        $this->resetAfterTest();
        $this->create_cities();

        $result = api::search('cities', '1500800');
        $this->assertSame(['1500800'], $this->codes($result));
        $this->assertSame('Ananindeua', $result['items'][0]['label']);
    }

    /**
     * The parent code filter keeps the children of one parent only.
     */
    public function test_parent_filter(): void {
        $this->resetAfterTest();
        $this->create_cities();

        $this->assertSame(['1500800', '1501402'], $this->codes(api::search('cities', '', 20, 'pa', 'uf')));
        $this->assertSame([], $this->codes(api::search('cities', 'campinas', 20, 'PA', 'uf')));
        // Control: without the filter, Campinas is found.
        $this->assertSame(['3509502'], $this->codes(api::search('cities', 'campinas')));
    }

    /**
     * The limit cuts the result and reports that more match; sortorder comes before the label.
     */
    public function test_limit_hasmore_and_sortorder(): void {
        $this->resetAfterTest();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'letters']);
        $generator->create_item(['domain' => 'letters', 'code' => 'a', 'label' => 'Alpha', 'sortorder' => 2]);
        $generator->create_item(['domain' => 'letters', 'code' => 'b', 'label' => 'Bravo', 'sortorder' => 1]);
        $generator->create_item(['domain' => 'letters', 'code' => 'c', 'label' => 'Charlie', 'sortorder' => 1]);

        $result = api::search('letters', '', 2);
        $this->assertSame(['b', 'c'], $this->codes($result));
        $this->assertTrue($result['hasmore']);

        $result = api::search('letters', '', 3);
        $this->assertSame(['b', 'c', 'a'], $this->codes($result));
        $this->assertFalse($result['hasmore']);
    }

    /**
     * Wildcards typed by the user are literal characters.
     */
    public function test_like_wildcards_are_escaped(): void {
        $this->resetAfterTest();
        $this->create_cities();

        $this->assertSame([], $this->codes(api::search('cities', '%')));
        $this->assertSame([], $this->codes(api::search('cities', '_')));
    }

    /**
     * Unknown and invalid domains give an empty result instead of an error.
     */
    public function test_unknown_domain_degrades(): void {
        $this->resetAfterTest();

        $this->assertSame(['items' => [], 'hasmore' => false], api::search('nowhere', 'x'));
        $this->assertSame(['items' => [], 'hasmore' => false], api::search('Bad Domain!', 'x'));
    }

    /**
     * The parent is looked up in the named domain only: a code that exists in two domains
     * matches the items whose parent is the one in the named domain.
     */
    public function test_parent_code_in_two_domains_matches_only_the_named_one(): void {
        $this->resetAfterTest();
        $this->create_cities();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        // A second domain that also holds a 'SP' item, with a child of the same 'cities' domain.
        $generator->create_domain(['shortname' => 'regions']);
        $generator->create_item(['domain' => 'regions', 'code' => 'SP', 'label' => 'South Pole']);
        $generator->create_item([
            'domain' => 'cities',
            'code' => 'ice',
            'label' => 'Ice city',
            'parentcode' => 'SP',
            'parentdomain' => 'regions',
        ]);

        $this->assertSame(['ice'], $this->codes(api::search('cities', '', 20, 'SP', 'regions')));
        $this->assertSame(
            ['3509502', '3548708', '3550308'],
            $this->codes(api::search('cities', '', 20, 'SP', 'uf'))
        );
        // Without a parent domain the parent is looked up in the searched domain, which has no 'SP'.
        $this->assertSame([], $this->codes(api::search('cities', '', 20, 'SP')));
        // An unknown parent domain gives nothing.
        $this->assertSame([], $this->codes(api::search('cities', '', 20, 'SP', 'nowhere')));
    }

    /**
     * An open domain: any logged-in user reads it, the guest does not, and visibility is read
     * from the recorded value.
     */
    public function test_can_read_open_domain(): void {
        $this->resetAfterTest();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'open', 'visibility' => api::VISIBILITY_LOGGEDIN]);
        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->assertTrue(api::can_read('open'));
        $this->assertTrue(api::can_read('OPEN'));
        $this->assertTrue(api::can_read('open', (int)$user->id));
        api::require_read('open');

        $this->setGuestUser();
        $this->assertFalse(api::can_read('open'));
        $this->assertFalse(api::can_read('open', (int)guest_user()->id));
        $this->expectException(\moodle_exception::class);
        api::require_read('open');
    }

    /**
     * With nobody logged in, an open domain is neither readable nor passes the require_read() gate.
     */
    public function test_open_domain_refuses_anonymous(): void {
        $this->resetAfterTest();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'open', 'visibility' => api::VISIBILITY_LOGGEDIN]);

        // Control: a logged-in user passes.
        $this->setUser($this->getDataGenerator()->create_user());
        api::require_read('open');

        $this->setUser(0);
        $this->assertFalse(api::can_read('open'));
        $this->expectException(\moodle_exception::class);
        api::require_read('open');
    }

    /**
     * A restricted domain needs local/dictionaries:view, for the current user and for a named one.
     */
    public function test_can_read_restricted_domain(): void {
        $this->resetAfterTest();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'restricted', 'visibility' => api::VISIBILITY_RESTRICTED]);
        $user = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/dictionaries:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $viewer->id, \context_system::instance()->id);

        $this->setUser($user);
        $this->assertFalse(api::can_read('restricted'));
        $this->assertFalse(api::can_read('restricted', (int)$user->id));
        $this->assertTrue(api::can_read('restricted', (int)$viewer->id));

        $this->setUser($viewer);
        $this->assertTrue(api::can_read('restricted'));
        api::require_read('restricted');

        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        api::require_read('restricted');
    }

    /**
     * An unknown or invalid domain is treated as restricted, so it cannot be told from one.
     */
    public function test_can_read_unknown_domain_needs_view(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertFalse(api::can_read('nowhere'));
        $this->assertFalse(api::can_read('Bad Domain!'));
        $this->assertFalse(api::can_read(''));

        $this->setAdminUser();
        $this->assertTrue(api::can_read('nowhere'));
    }
}
