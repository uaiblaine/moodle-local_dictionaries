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
 * Transport for core/form-autocomplete that searches one reference dictionary domain.
 *
 * The enhanced select carries data-domain (the domain shortname) and, optionally,
 * data-parentcode (only items whose parent has that code) and data-parentdomain (the domain
 * holding the parents; the searched domain when absent). All are read on every query, so a
 * page may change data-parentcode when a parent field changes. Option values are codes;
 * option labels read "label (code)".
 *
 * @module     local_dictionaries/autocomplete_transport
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Escape text for HTML: core/form-autocomplete inserts option labels as markup.
 *
 * @param {String} text
 * @returns {String}
 */
const escapeHtml = (text) => {
    const element = document.createElement('span');
    element.textContent = text;
    return element.innerHTML;
};

/**
 * Search the domain named by the select element.
 *
 * @param {String} selector The selector of the original select element.
 * @param {String} query The text typed by the user.
 * @param {Function} callback Receives the web service response.
 * @param {Function} failure Receives the error.
 */
export const transport = async(selector, query, callback, failure) => {
    const element = document.querySelector(selector);
    const domain = element ? (element.dataset.domain || '') : '';
    if (domain === '') {
        callback({items: [], hasmore: false});
        return;
    }

    try {
        const response = await Ajax.call([{
            methodname: 'local_dictionaries_search_items',
            args: {
                domain: domain,
                query: query,
                parentcode: element.dataset.parentcode || '',
                parentdomain: element.dataset.parentdomain || '',
            },
        }])[0];
        callback(response);
    } catch (error) {
        failure(error);
    }
};

/**
 * Turn the web service response into autocomplete options.
 *
 * @param {String} selector The selector of the original select element.
 * @param {Object|String} results The response passed to the callback by transport().
 * @returns {Array|String} Options, or a notice string passed through unchanged.
 */
export const processResults = (selector, results) => {
    if (!results || !Array.isArray(results.items)) {
        return results;
    }
    return results.items.map((item) => ({
        value: item.code,
        label: escapeHtml(`${item.label} (${item.code})`),
    }));
};
