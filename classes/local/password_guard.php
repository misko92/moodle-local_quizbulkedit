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

namespace local_quizbulkedit\local;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

/**
 * Keeps the quiz password a teacher types on a student's computer out of sight.
 *
 * The quiz password prompt (quizaccess_password) uses a passwordunmask field,
 * which has a "Reveal" eye button. The prompt is a pop-up on the quiz view page,
 * so after the quiz starts the browser's back/forward cache still holds that page
 * with the password typed in: a student pressing Back could reveal it. On student
 * quiz pages this hides the eye button, empties the field as the page is left,
 * and reloads the page if it is restored from the back/forward cache.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class password_guard {
    /**
     * Whether to guard the current page.
     *
     * @return bool
     */
    protected static function applies(): bool {
        global $PAGE;
        if (during_initial_install() || !get_config('local_quizbulkedit', 'passwordguard')) {
            return false;
        }
        // Student-facing quiz pages only; mod-quiz-mod is the teacher's settings form.
        $pagetype = $PAGE->pagetype;
        return str_starts_with($pagetype, 'mod-quiz-') && $pagetype !== 'mod-quiz-mod';
    }

    /**
     * Hide the Reveal button of the quiz password prompt before the page paints.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        if (!self::applies()) {
            return;
        }
        $hook->add_html('<style>' .
            '[data-passwordunmaskid="id_quizpassword"] [data-passwordunmask="unmask"] { display: none !important; }' .
            '#id_quizpassword::-ms-reveal { display: none; }' .
            '</style>');
    }

    /**
     * Load the script that clears the prompt when the page is left.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::applies()) {
            return;
        }
        $PAGE->requires->js_call_amd('local_quizbulkedit/passwordguard', 'init');
    }
}
