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

namespace local_dictionaries\form;

/**
 * Tests for unique submit-button markup across the manage page forms.
 *
 * @package    local_dictionaries
 * @covers     \local_dictionaries\form\create_domain_form
 * @covers     \local_dictionaries\form\import_csv_form
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submit_buttons_test extends \advanced_testcase {
    /**
     * Both forms render on manage.php: their submit buttons must not share DOM ids.
     */
    public function test_submit_button_ids_are_unique_across_manage_forms(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = (new create_domain_form('/local/dictionaries/manage.php'))->render()
            . (new import_csv_form('/local/dictionaries/manage.php'))->render();

        $this->assertSame(0, substr_count($html, 'id="id_submitbutton"'));
        $this->assertSame(1, substr_count($html, 'id="id_savedomainbutton"'));
        $this->assertSame(1, substr_count($html, 'id="id_importitemsbutton"'));
    }
}
