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

use local_dictionaries\local\manager;

/**
 * Tests for import_csv_form validation.
 *
 * Metadata stays in docblocks: the plugin supports Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_dictionaries
 * @category   test
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

    /**
     * An invalid parent domain shortname is rejected; an empty one is allowed.
     */
    public function test_validation_checks_parent_domain(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new import_csv_form('/local/dictionaries/manage.php');
        $errors = $form->validation([
            'importdomainshortname' => 'cities',
            'importparentdomain' => 'Bad Parent!',
            'csvtext' => '01,Sao Paulo',
        ], []);
        $this->assertArrayHasKey('importparentdomain', $errors);

        $errors = $form->validation([
            'importdomainshortname' => 'cities',
            'importparentdomain' => '',
            'csvtext' => '01,Sao Paulo',
        ], []);
        $this->assertArrayNotHasKey('importparentdomain', $errors);
    }

    /**
     * Pasted CSV larger than MAX_CSV_BYTES is refused; text of exactly that size is not.
     */
    public function test_validation_refuses_oversized_pasted_csv(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new import_csv_form('/local/dictionaries/manage.php');
        $atlimit = str_pad('01,Sao Paulo', manager::MAX_CSV_BYTES);
        $errors = $form->validation(['importdomainshortname' => 'cities', 'csvtext' => $atlimit], []);
        $this->assertArrayNotHasKey('csvtext', $errors);

        $errors = $form->validation(['importdomainshortname' => 'cities', 'csvtext' => $atlimit . ' '], []);
        $this->assertSame(
            get_string('csvtoolarge', 'local_dictionaries', display_size(manager::MAX_CSV_BYTES)),
            $errors['csvtext'] ?? null
        );
    }

    /**
     * An uploaded file larger than MAX_CSV_BYTES is refused; one of exactly that size is not.
     *
     * The picker's own limit travels with the upload request, so validation is what holds it.
     */
    public function test_validation_refuses_oversized_file(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ([manager::MAX_CSV_BYTES => false, manager::MAX_CSV_BYTES + 1 => true] as $size => $refused) {
            $draftid = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftid,
                'filepath' => '/',
                'filename' => 'items.csv',
            ], str_pad('01,Sao Paulo', $size));
            $data = ['importdomainshortname' => 'cities', 'csvfile' => $draftid, 'action' => 'importcsv'];
            import_csv_form::mock_submit($data);

            $form = new import_csv_form('/local/dictionaries/manage.php');
            $errors = $form->validation($data, []);
            $this->assertArrayNotHasKey('csvtext', $errors, "size $size");
            if ($refused) {
                $this->assertSame(
                    get_string('csvtoolarge', 'local_dictionaries', display_size(manager::MAX_CSV_BYTES)),
                    $errors['csvfile'] ?? null
                );
            } else {
                $this->assertArrayNotHasKey('csvfile', $errors, "size $size");
            }
        }
    }
}
