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
use local_dictionaries\api;
use local_dictionaries\local\manager;
use local_dictionaries\resolver;
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
     *
     * @return void
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
                'maxlength' => manager::MAX_SHORTNAME_LENGTH,
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
                'maxlength' => manager::MAX_NAME_LENGTH,
                'size' => 60,
                'placeholder' => get_string('placeholderdomainname', 'local_dictionaries'),
            ]
        );
        $mform->setType('domainname', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'domaindescription',
            get_string('domaindescription', 'local_dictionaries'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('domaindescription', PARAM_TEXT);

        $mform->addElement(
            'select',
            'domainvisibility',
            get_string('domainvisibility', 'local_dictionaries'),
            [
                api::VISIBILITY_RESTRICTED => get_string('visibilityrestricted', 'local_dictionaries'),
                api::VISIBILITY_LOGGEDIN => get_string('visibilityloggedin', 'local_dictionaries'),
            ]
        );
        $mform->setType('domainvisibility', PARAM_INT);
        $mform->setDefault('domainvisibility', api::VISIBILITY_RESTRICTED);
        $mform->addHelpButton('domainvisibility', 'domainvisibility', 'local_dictionaries');

        $mform->addElement('hidden', 'action', 'createdomain');
        $mform->setType('action', PARAM_ALPHA);

        // A label distinct from the header's, or screen readers and Behat cannot tell the collapse
        // toggle from the submit button. A named element rather than add_action_buttons(): two
        // moodleforms share manage.php, and both default 'submitbutton' names would share one DOM id.
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
        } else if (core_text::strlen($shortname) > manager::MAX_SHORTNAME_LENGTH) {
            $errors['domainshortname'] = get_string('domaintoolong', 'local_dictionaries', manager::MAX_SHORTNAME_LENGTH);
        } else if (!preg_match(resolver::DOMAIN_PATTERN, $shortname)) {
            $errors['domainshortname'] = get_string('domaininvalid', 'local_dictionaries');
        }

        $visibility = (int)($data['domainvisibility'] ?? api::VISIBILITY_RESTRICTED);
        if (!in_array($visibility, [api::VISIBILITY_RESTRICTED, api::VISIBILITY_LOGGEDIN], true)) {
            $errors['domainvisibility'] = get_string('domainvisibilityinvalid', 'local_dictionaries');
        }

        return $errors;
    }
}
