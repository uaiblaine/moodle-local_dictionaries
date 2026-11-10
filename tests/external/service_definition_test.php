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

/**
 * Tests for the predefined external service of local_dictionaries.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dictionaries\external;

use core_external\external_api;

/**
 * Tests for the predefined external service of local_dictionaries.
 *
 * Reads db/services.php itself rather than the installed row, so a change to the file is seen
 * without reinstalling the test site. Metadata stays in docblocks: the plugin supports Moodle
 * 4.5, whose moodle-cs cannot read PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
 * @coversNothing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class service_definition_test extends \advanced_testcase {
    /**
     * Read the $functions and $services arrays of db/services.php.
     *
     * @return array [$functions, $services]
     */
    private function definitions(): array {
        global $CFG;

        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/dictionaries/db/services.php');
        return [$functions, $services];
    }

    /**
     * The predefined service starts disabled and limited to authorised users.
     */
    public function test_service_is_disabled_and_restricted(): void {
        [, $services] = $this->definitions();

        $this->assertArrayHasKey('Reference dictionaries API', $services);
        $service = $services['Reference dictionaries API'];
        $this->assertSame('local_dictionaries_api', $service['shortname']);
        $this->assertSame(0, $service['enabled']);
        $this->assertSame(1, $service['restrictedusers']);
    }

    /**
     * Every function stays callable through AJAX, which does not need the service enabled.
     */
    public function test_functions_stay_available_to_ajax(): void {
        $this->resetAfterTest();
        [$functions, $services] = $this->definitions();

        $names = $services['Reference dictionaries API']['functions'];
        $this->assertEqualsCanonicalizing(array_keys($functions), $names);
        foreach ($names as $name) {
            $this->assertTrue($functions[$name]['ajax'], $name);
            $this->assertTrue((bool)external_api::external_function_info($name)->allowed_from_ajax, $name);
        }
    }
}
