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
 * Tests for the get_reference_labels web service.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @covers     \local_dictionaries\external\get_reference_labels
 * @covers     \local_dictionaries\local\access
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_reference_labels_test extends \advanced_testcase {
    /**
     * Create a restricted and an open domain, each with item '01'.
     *
     * @return void
     */
    private function create_domains(): void {
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'restricted', 'visibility' => api::VISIBILITY_RESTRICTED]);
        $generator->create_item(['domain' => 'restricted', 'code' => '01', 'label' => 'Secret']);
        $generator->create_domain(['shortname' => 'open', 'visibility' => api::VISIBILITY_LOGGEDIN]);
        $generator->create_item(['domain' => 'open', 'code' => '01', 'label' => 'Public']);
    }

    /**
     * Call the function the way an AJAX request does.
     *
     * @param array $args
     * @return array The call_external_function() response.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('local_dictionaries_get_reference_labels', $args, true);
    }

    /**
     * Returns labels for known codes only; unknown codes are left out.
     */
    public function test_execute_returns_resolved_labels(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setAdminUser();

        $result = get_reference_labels::execute('restricted', ['01', '99']);
        $result = external_api::clean_returnvalue(get_reference_labels::execute_returns(), $result);
        $this->assertSame([['code' => '01', 'label' => 'Secret']], $result['items']);
    }

    /**
     * A user without the view capability reads an open domain but not a restricted or unknown one.
     */
    public function test_visibility_decides_on_the_recorded_domain(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'codes' => ['01']]);
        $this->assertFalse($response['error']);
        $this->assertSame([['code' => '01', 'label' => 'Public']], $response['data']['items']);

        $response = $this->call(['domain' => 'restricted', 'codes' => ['01']]);
        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);

        $response = $this->call(['domain' => 'nowhere', 'codes' => ['01']]);
        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);
    }

    /**
     * Labels leave the service with markup stripped and no HTML escaping; the stored value stays raw.
     */
    public function test_labels_are_formatted_not_raw(): void {
        global $DB;

        $this->resetAfterTest();
        $this->create_domains();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_item(['domain' => 'open', 'code' => 'ml', 'label' => '<b>Bold</b> & <script>x</script>y']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call(['domain' => 'open', 'codes' => ['ml']]);
        $this->assertFalse($response['error']);
        $label = $response['data']['items'][0]['label'];
        $this->assertStringNotContainsString('<', $label);
        $this->assertStringContainsString('&', $label);
        $this->assertStringNotContainsString('&amp;', $label);
        $this->assertStringContainsString('<b>', $DB->get_field('local_dictionaries_item', 'label', ['codekey' => 'ml']));
    }

    /**
     * The view capability opens restricted domains, but does not allow writes.
     */
    public function test_view_capability_reads_restricted_domains(): void {
        $this->resetAfterTest();
        $this->create_domains();

        $systemcontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'dictreader']);
        assign_capability('local/dictionaries:view', CAP_ALLOW, $roleid, $systemcontext->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $systemcontext->id);
        $this->setUser($user);

        $response = $this->call(['domain' => 'restricted', 'codes' => ['01']]);
        $this->assertFalse($response['error']);
        $this->assertSame('Secret', $response['data']['items'][0]['label']);

        $this->expectException(\required_capability_exception::class);
        upsert_reference_items::execute('restricted', [['code' => '02', 'label' => 'Rio']]);
    }

    /**
     * The guest user is refused even on an open domain; an anonymous caller is refused before that.
     */
    public function test_guest_and_anonymous_are_refused(): void {
        $this->resetAfterTest();
        $this->create_domains();

        $this->setGuestUser();
        $response = $this->call(['domain' => 'open', 'codes' => ['01']]);
        $this->assertTrue($response['error']);
        $this->assertSame('noguest', $response['exception']->errorcode);

        $this->setUser(null);
        $response = $this->call(['domain' => 'open', 'codes' => ['01']]);
        $this->assertTrue($response['error']);
        $this->assertSame('servicerequireslogin', $response['exception']->errorcode);
    }

    /**
     * Throws when the batch size exceeds the maximum.
     */
    public function test_execute_rejects_oversized_batch(): void {
        $this->resetAfterTest();
        $this->create_domains();
        $this->setAdminUser();

        $codes = array_fill(0, \local_dictionaries\local\manager::MAX_BATCH_SIZE + 1, 'x');

        $this->expectException(\invalid_parameter_exception::class);
        get_reference_labels::execute('restricted', $codes);
    }
}
