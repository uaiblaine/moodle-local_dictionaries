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
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or update one reference domain.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_domain_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'createdomainheader',
            get_string('createdomain', 'local_dictionaries')
        );

        $mform->addElement(
            'text',
            'domainshortname',
            get_string('domainshortname', 'local_dictionaries'),
            [
                'maxlength' => 100,
                'size' => 40,
                'placeholder' => get_string('placeholderdomainshortname', 'local_dictionaries'),
            ]
        );
        $mform->setType('domainshortname', PARAM_RAW_TRIMMED);
        $mform->addRule(
            'domainshortname',
            get_string('domainrequired', 'local_dictionaries'),
            'required',
            null,
            'client'
        );

        $mform->addElement(
            'text',
            'domainname',
            get_string('domainname', 'local_dictionaries'),
            [
                'maxlength' => 255,
                'size' => 60,
                'placeholder' => get_string('placeholderdomainname', 'local_dictionaries'),
            ]
        );
        $mform->setType('domainname', PARAM_TEXT);

        $mform->addElement('hidden', 'action', 'createdomain');
        $mform->setType('action', PARAM_ALPHA);

        /* Distinct from the section header label: identical strings make the
         * collapse toggle and the submit button indistinguishable for
         * screen readers and UI tests alike. Named element instead of
         * add_action_buttons(): two moodleforms share manage.php, and the
         * default 'submitbutton' name would duplicate the DOM id. */
        $mform->addElement('submit', 'savedomainbutton', get_string('savedomain', 'local_dictionaries'));
        $mform->closeHeaderBefore('savedomainbutton');
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $shortname = core_text::strtolower(trim((string)($data['domainshortname'] ?? '')));
        if ($shortname === '') {
            $errors['domainshortname'] = get_string('domainrequired', 'local_dictionaries');
        } else if (!preg_match('/^[a-z0-9_]+$/', $shortname)) {
            $errors['domainshortname'] = get_string('domaininvalid', 'local_dictionaries');
        }

        return $errors;
    }
}
