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
 * Keep a typed quiz password out of the browser's back/forward cache.
 *
 * See \local_quizbulkedit\local\password_guard.
 *
 * @module     local_quizbulkedit/passwordguard
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Empty every quiz password prompt on the page.
 */
const clearPasswords = () => {
    document.querySelectorAll('input[name="quizpassword"]').forEach((input) => {
        input.value = '';
    });
    // The passwordunmask field also shows a masked copy of the value.
    document.querySelectorAll('[data-passwordunmaskid="id_quizpassword"] [data-passwordunmask="displayvalue"]')
        .forEach((display) => {
            display.textContent = '';
        });
};

/**
 * Initialise.
 */
export const init = () => {
    document.querySelectorAll('input[name="quizpassword"]').forEach((input) => {
        input.setAttribute('autocomplete', 'off');
    });
    // Runs before the page goes into the back/forward cache, so the cached copy is already empty.
    window.addEventListener('pagehide', clearPasswords);
    // If the page comes back from that cache anyway (e.g. with the pop-up still open), start afresh.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            clearPasswords();
            window.location.reload();
        }
    });
};
