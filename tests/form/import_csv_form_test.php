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
 * Tests for import_csv_form validation.
 *
 * @package    local_dictionaries
 * @covers     \local_dictionaries\form\import_csv_form
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_csv_form_test extends \advanced_testcase {
    /**
     * Invalid domain shortnames must be rejected before reaching the manager.
     */
    public function test_validation_rejects_invalid_domain_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new import_csv_form('/local/dictionaries/manage.php');
        $errors = $form->validation([
            'importdomainshortname' => 'Bad Domain!',
            'csvtext' => '01,Sao Paulo',
        ], []);

        $this->assertArrayHasKey('importdomainshortname', $errors);
        $this->assertSame(
            get_string('domaininvalid', 'local_dictionaries'),
            $errors['importdomainshortname']
        );
    }

    /**
     * Valid domain shortname with pasted CSV passes validation.
     */
    public function test_validation_accepts_valid_domain_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new import_csv_form('/local/dictionaries/manage.php');
        $errors = $form->validation([
            'importdomainshortname' => 'diretoria',
            'csvtext' => '01,Sao Paulo',
        ], []);

        $this->assertArrayNotHasKey('importdomainshortname', $errors);
    }
}
