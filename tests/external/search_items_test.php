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
use local_dictionaries\api;

/**
 * Tests for the search_items web service.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\external\search_items
 * @covers     \local_dictionaries\local\access
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_items_test extends \advanced_testcase {
    /**
     * Create an open and a restricted domain with matching items.
     *
     * @return void
     */
    private function create_domains(): void {
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'open', 'visibility' => api::VISIBILITY_LOGGEDIN]);
        $generator->create_item(['domain' => 'open', 'code' => 'pt', 'label' => 'Portuguese']);
        $generator->create_item(['domain' => 'open', 'code' => 'pl', 'label' => 'Polish']);
        $generator->create_domain(['shortname' => 'restricted']);
        $generator->create_item(['domain' => 'restricted', 'code' => 'x', 'label' => 'Secret']);
    }

    /**
     * Call the function the way an AJAX request does.
     *
     * @param array $args
     * @return array The call_external_function() response.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('local_dictionaries_search_items', $args, true);
    }

    /**
     * Any logged-in user searches an open domain; the result honours the limit.
     */
    public function test_open_domain_for_any_user(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'query' => 'p', 'limit' => 1]);
        $this->assertFalse($response['error']);
        $this->assertSame([['code' => 'pl', 'label' => 'Polish']], $response['data']['items']);
        $this->assertTrue($response['data']['hasmore']);
    }

    /**
     * A restricted domain needs the view capability: a plain user is refused, the admin is not.
     */
    public function test_restricted_domain_needs_view_capability(): void {
        $this->resetAfterTest();
        $this->create_domains();

        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->call(['domain' => 'restricted', 'query' => 'secret']);
        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);

        $this->setAdminUser();
        $response = $this->call(['domain' => 'restricted', 'query' => 'secret']);
        $this->assertFalse($response['error']);
        $this->assertSame([['code' => 'x', 'label' => 'Secret']], $response['data']['items']);
    }

    /**
     * Turning a domain open takes effect at once: the decision reads the recorded visibility.
     */
    public function test_visibility_change_applies_immediately(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertTrue($this->call(['domain' => 'restricted', 'query' => 'x'])['error']);
        (new \local_dictionaries\local\manager())->upsert_domain('restricted', 'Restricted', null, api::VISIBILITY_LOGGEDIN);
        $this->assertFalse($this->call(['domain' => 'restricted', 'query' => 'x'])['error']);
    }

    /**
     * The guest user is refused even on an open domain.
     */
    public function test_guest_is_refused(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setGuestUser();

        $response = $this->call(['domain' => 'open', 'query' => 'p']);
        $this->assertTrue($response['error']);
        $this->assertSame('noguest', $response['exception']->errorcode);
    }

    /**
     * The function is registered for AJAX with a read-only session.
     */
    public function test_function_registration(): void {
        $this->resetAfterTest();

        $info = external_api::external_function_info('local_dictionaries_search_items');
        $this->assertTrue((bool)$info->allowed_from_ajax);
        $this->assertTrue((bool)$info->readonlysession);
        $this->assertSame('read', $info->type);
    }

    /**
     * An open domain whose items have parents in a restricted domain: the parent domain is
     * gated too, so the filter cannot probe the restricted codes.
     */
    public function test_restricted_parent_domain_is_refused(): void {
        $this->resetAfterTest();
        $this->create_domains();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_item([
            'domain' => 'open',
            'code' => 'child',
            'label' => 'Child',
            'parentcode' => 'x',
            'parentdomain' => 'restricted',
        ]);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'query' => '', 'parentcode' => 'x', 'parentdomain' => 'restricted']);
        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);

        // Control: the admin, who holds the view capability, may name the restricted parent domain.
        $this->setAdminUser();
        $response = $this->call(['domain' => 'open', 'query' => '', 'parentcode' => 'x', 'parentdomain' => 'restricted']);
        $this->assertFalse($response['error']);
        $this->assertSame([['code' => 'child', 'label' => 'Child']], $response['data']['items']);
    }

    /**
     * Without a parent domain the parent is looked up in the searched domain, so a code that
     * exists only in a restricted domain matches nothing.
     */
    public function test_parent_code_defaults_to_the_searched_domain(): void {
        $this->resetAfterTest();
        $this->create_domains();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_item([
            'domain' => 'open',
            'code' => 'child',
            'label' => 'Child',
            'parentcode' => 'x',
            'parentdomain' => 'restricted',
        ]);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'query' => '', 'parentcode' => 'x']);
        $this->assertFalse($response['error']);
        $this->assertSame([], $response['data']['items']);
    }

    /**
     * Labels leave the service through format_string(): tags stripped, '&' not escaped; the stored value stays raw.
     */
    public function test_labels_are_formatted_not_raw(): void {
        global $DB;

        $this->resetAfterTest();
        $this->create_domains();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_item(['domain' => 'open', 'code' => 'ml', 'label' => '<b>Bold</b> & <script>x</script>y']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'query' => 'ml']);
        $this->assertFalse($response['error']);
        $label = $response['data']['items'][0]['label'];
        $this->assertStringNotContainsString('<', $label);
        $this->assertStringContainsString('&', $label);
        $this->assertStringNotContainsString('&amp;', $label);
        $this->assertStringContainsString('<b>', $DB->get_field('local_dictionaries_item', 'label', ['codekey' => 'ml']));
    }
}
