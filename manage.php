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
 * Domain and CSV management page.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_dictionaries\local\domain_rows;
use local_dictionaries\local\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_dictionaries_manage');

$context = context_system::instance();
require_capability('local/dictionaries:manage', $context);

$PAGE->set_url(new moodle_url('/local/dictionaries/manage.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managedictionaries', 'local_dictionaries'));
$PAGE->set_heading(get_string('managedictionaries', 'local_dictionaries'));

$edit = optional_param('edit', '', PARAM_ALPHANUMEXT);

$manager = new manager();
$error = '';

$createform = new \local_dictionaries\form\create_domain_form($PAGE->url->out(false));
$importform = new \local_dictionaries\form\import_csv_form($PAGE->url->out(false));

if ($createdata = $createform->get_data()) {
    try {
        $domain = $manager->upsert_domain(
            (string)$createdata->domainshortname,
            (string)($createdata->domainname ?? ''),
            (string)($createdata->domaindescription ?? ''),
            (int)$createdata->domainvisibility
        );
        redirect($PAGE->url, get_string('domainsaved', 'local_dictionaries', $domain->shortname));
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

if ($importdata = $importform->get_data()) {
    try {
        $csvcontent = $importform->get_csv_content($importdata);
        if (trim($csvcontent) === '') {
            throw new moodle_exception('csvrequired', 'local_dictionaries');
        }

        $parsed = $manager->parse_csv_content($csvcontent);
        $parentdomain = trim((string)($importdata->importparentdomain ?? ''));
        $counts = $manager->upsert_items_chunked(
            (string)$importdata->importdomainshortname,
            $parsed['items'],
            null,
            $parentdomain === '' ? null : $parentdomain
        );

        $summary = (object)($counts + [
            'duplicates' => $parsed['duplicates'],
            'rejected' => count($parsed['rejected']),
        ]);
        $message = get_string('importsummary', 'local_dictionaries', $summary);
        $type = \core\output\notification::NOTIFY_SUCCESS;
        if (!empty($parsed['rejected'])) {
            $rows = [];
            foreach (array_slice($parsed['rejected'], 0, manager::MAX_REJECTIONS_SHOWN) as $rejection) {
                $line = (int)$rejection['line'];
                $rows[] = ['text' => match ($rejection['reason']) {
                    manager::REJECT_INVALID_UTF8 => get_string('csvrejectinvalidutf8', 'local_dictionaries', $line),
                    manager::REJECT_TOO_FEW_COLUMNS => get_string('csvrejecttoofewcolumns', 'local_dictionaries', $line),
                    manager::REJECT_EMPTY_CODE => get_string('csvrejectemptycode', 'local_dictionaries', $line),
                    manager::REJECT_CODE_TOO_LONG => get_string('csvrejectcodetoolong', 'local_dictionaries', $line),
                    manager::REJECT_INVALID_SORTORDER => get_string('csvrejectinvalidsortorder', 'local_dictionaries', $line),
                    manager::REJECT_INVALID_ACTIVE => get_string('csvrejectinvalidactive', 'local_dictionaries', $line),
                }];
            }
            $hidden = count($parsed['rejected']) - count($rows);
            /* The redirect notification prints its message as HTML; the template escapes every
             * value, and no rejected row echoes file content back, only a line number. */
            $message = $OUTPUT->render_from_template('local_dictionaries/import_report', [
                'summary' => $message,
                'rows' => $rows,
                'hashidden' => $hidden > 0,
                'hidden' => $hidden,
            ]);
            $type = \core\output\notification::NOTIFY_WARNING;
        }
        redirect($PAGE->url, $message, null, $type);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$domains = [];
try {
    $domains = $manager->list_domains();
    if ($edit !== '' && !$createform->is_submitted()) {
        $editing = $manager->get_domain($edit);
        if ($editing) {
            $createform->set_data([
                'domainshortname' => (string)$editing->shortname,
                'domainname' => (string)$editing->name,
                'domaindescription' => (string)($editing->description ?? ''),
                'domainvisibility' => (int)$editing->visibility,
            ]);
        }
    }
} catch (moodle_exception $e) {
    $error = $e->getMessage();
}

echo $OUTPUT->header();

if ($error !== '') {
    /* Notifications render their message as raw HTML; escape it because
     * exception messages can carry request-derived values. */
    echo $OUTPUT->notification(s($error), \core\output\notification::NOTIFY_ERROR);
}

$createform->display();
$importform->display();

$domainrows = domain_rows::build($domains, $context, $PAGE->url);

echo $OUTPUT->render_from_template('local_dictionaries/manage', [
    'hasdomains' => !empty($domainrows),
    'domains' => $domainrows,
]);

echo $OUTPUT->footer();
