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

use core_text;
use local_dictionaries\local\manager;
use local_dictionaries\resolver;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to import code/label pairs from CSV.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_csv_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'importcsvheader',
            get_string('importcsv', 'local_dictionaries')
        );

        $mform->addElement(
            'text',
            'importdomainshortname',
            get_string('domainshortname', 'local_dictionaries'),
            [
                'maxlength' => manager::MAX_SHORTNAME_LENGTH,
                'size' => 40,
                'placeholder' => get_string('placeholderdomainshortname', 'local_dictionaries'),
            ]
        );
        $mform->setType('importdomainshortname', PARAM_RAW_TRIMMED);
        $mform->addRule(
            'importdomainshortname',
            get_string('domainrequired', 'local_dictionaries'),
            'required',
            null,
            'client'
        );

        $mform->addElement(
            'text',
            'importparentdomain',
            get_string('parentdomain', 'local_dictionaries'),
            [
                'maxlength' => manager::MAX_SHORTNAME_LENGTH,
                'size' => 40,
            ]
        );
        $mform->setType('importparentdomain', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('importparentdomain', 'parentdomain', 'local_dictionaries');

        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csvfile', 'local_dictionaries'),
            null,
            ['accepted_types' => ['.csv', '.txt'], 'maxbytes' => 0]
        );

        $mform->addElement(
            'textarea',
            'csvtext',
            get_string('csvtext', 'local_dictionaries'),
            ['rows' => 8, 'cols' => 80, 'style' => 'font-family:monospace']
        );
        $mform->setType('csvtext', PARAM_RAW);

        $mform->addElement(
            'static',
            'csvhelp',
            '',
            get_string('csvhelp', 'local_dictionaries')
        );

        $mform->addElement('hidden', 'action', 'importcsv');
        $mform->setType('action', PARAM_ALPHA);

        // The submit button has its own label and name; see create_domain_form::definition().
        $mform->addElement('submit', 'importitemsbutton', get_string('importitems', 'local_dictionaries'));
        $mform->closeHeaderBefore('importitemsbutton');
    }

    /**
     * Validate the domain shortnames and that a file or pasted CSV was provided.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $shortname = core_text::strtolower(trim((string)($data['importdomainshortname'] ?? '')));
        if ($shortname === '') {
            $errors['importdomainshortname'] = get_string('domainrequired', 'local_dictionaries');
        } else if (!preg_match(resolver::DOMAIN_PATTERN, $shortname)) {
            $errors['importdomainshortname'] = get_string('domaininvalid', 'local_dictionaries');
        }

        $parent = core_text::strtolower(trim((string)($data['importparentdomain'] ?? '')));
        if ($parent !== '' && !preg_match(resolver::DOMAIN_PATTERN, $parent)) {
            $errors['importparentdomain'] = get_string('domaininvalid', 'local_dictionaries');
        }

        $hastext = trim((string)($data['csvtext'] ?? '')) !== '';
        if (!$hastext && empty($this->get_draft_files('csvfile'))) {
            $errors['csvfile'] = get_string('csvrequired', 'local_dictionaries');
        }

        return $errors;
    }

    /**
     * Read CSV content from uploaded file (if any), falling back to pasted text.
     *
     * @param object $data Submitted form data (from get_data()).
     * @return string
     */
    public function get_csv_content(object $data): string {
        $content = '';

        if (!empty($data->csvfile)) {
            $content = (string)$this->get_file_content('csvfile');
        }

        if (trim($content) === '') {
            $content = (string)($data->csvtext ?? '');
        }

        return $content;
    }
}
