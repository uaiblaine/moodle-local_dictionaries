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
 * Strings for component local_dictionaries, language 'en'.
 *
 * @package    local_dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['cachedef_domains'] = 'Reference dictionary domains read during one request';
$string['cachedef_labels'] = 'Resolved labels of reference dictionary codes';
$string['createdomain'] = 'Create or update domain';
$string['csvfile'] = 'CSV file';
$string['csvhelp'] = 'Columns: code, label. A first row naming the columns code and label is a header, and may add the columns parentcode, sortorder and active (0 or 1) in any order. The delimiter (comma, semicolon or tab) is detected from the first line. Codes are case- and accent-insensitive; a code repeated in the file keeps its last row.';
$string['csvrejectcodetoolong'] = 'Line {$a}: the code is longer than 255 characters.';
$string['csvrejectedhidden'] = '{$a} more rejected rows are not listed.';
$string['csvrejectemptycode'] = 'Line {$a}: the code is empty.';
$string['csvrejectinvalidactive'] = 'Line {$a}: active must be 0 or 1.';
$string['csvrejectinvalidsortorder'] = 'Line {$a}: sortorder must be a whole number.';
$string['csvrejectinvalidutf8'] = 'Line {$a}: the row is not valid UTF-8 text.';
$string['csvrejecttoofewcolumns'] = 'Line {$a}: the row has fewer columns than code and label need.';
$string['csvrequired'] = 'Upload a CSV file or paste CSV content.';
$string['csvtext'] = 'CSV content (optional)';
$string['dictionaries:manage'] = 'Manage reference dictionaries';
$string['dictionaries:view'] = 'Read restricted reference dictionaries through the web services';
$string['domaindescription'] = 'Description';
$string['domaininvalid'] = 'Invalid domain shortname. Use lowercase letters, numbers, and underscore only.';
$string['domainname'] = 'Domain name';
$string['domainrequired'] = 'Domain shortname is required.';
$string['domainsaved'] = 'Domain saved: {$a}';
$string['domainshortname'] = 'Domain shortname';
$string['domaintoolong'] = 'Domain shortname is longer than {$a} characters.';
$string['domainvisibility'] = 'Who can look up items';
$string['domainvisibility_help'] = 'Who may resolve and search the items of this domain through the web services (autocomplete fields, for example). Restricted domains need the capability local/dictionaries:view. Plugins that call the PHP API directly decide for themselves.';
$string['domainvisibilityinvalid'] = 'Choose who can look up items.';
$string['editdomain'] = 'Edit {$a}';
$string['errorcodecollision'] = 'The database already holds a code equal to "{$a}" under its collation rules (for example "ß" and "ss"). Rename one of them.';
$string['errorcodetoolong'] = 'The code of item {$a} is longer than 255 characters.';
$string['errornotables'] = 'The reference dictionary tables are not available yet. Run the plugin upgrade.';
$string['errorparentcycle'] = 'Item {$a} is part of a parent cycle.';
$string['errorparentself'] = 'Item {$a} cannot be its own parent.';
$string['errorunknowndomain'] = 'Unknown domain: {$a}';
$string['errorunknownparent'] = 'Unknown parent codes: {$a}';
$string['eventdomaincreated'] = 'Reference dictionary domain created';
$string['eventdomainupdated'] = 'Reference dictionary domain updated';
$string['eventitemsimported'] = 'Reference dictionary items imported';
$string['existingdomains'] = 'Existing domains';
$string['importcsv'] = 'Import CSV';
$string['importitems'] = 'Import items';
$string['importsummary'] = 'Import completed. Inserted: {$a->inserted}, Updated: {$a->updated}, Ignored: {$a->ignored}, Duplicates: {$a->duplicates}, Rejected: {$a->rejected}.';
$string['itemcount'] = 'Items';
$string['managedictionaries'] = 'Manage reference dictionaries';
$string['maxbatchsizeexceeded'] = 'Maximum batch size exceeded ({$a}).';
$string['name'] = 'Name';
$string['nodomains'] = 'No domains yet.';
$string['parentdomain'] = 'Parent domain (optional)';
$string['parentdomain_help'] = 'Shortname of the domain that holds the items named in the parentcode column, for example the states of a municipality list. Empty means the parents are items of the imported domain itself.';
$string['placeholderdomainname'] = 'Regional boards';
$string['placeholderdomainshortname'] = 'regional_boards';
$string['pluginname'] = 'Reference dictionaries';
$string['privacy:metadata'] = 'The Reference dictionaries plugin stores code lists entered by administrators. It stores no personal data.';
$string['savedomain'] = 'Save domain';
$string['shortname'] = 'Shortname';
$string['timemodified'] = 'Time modified';
$string['visibilityloggedin'] = 'Any logged-in user except guests';
$string['visibilityrestricted'] = 'Only users with the view capability';
