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
 * Edit the settings of all quizzes in a course on one page.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_quizbulkedit\local\updater;
use local_quizbulkedit\output\editor;

require(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$filter = optional_param('filter', '', PARAM_TEXT);
$url = new moodle_url('/local/quizbulkedit/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('bulkeditquizzes', 'local_quizbulkedit'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('bulkeditquizzes', 'local_quizbulkedit'), $url);

// Keep the keyword filter across the save redirect, but not in the page URL itself.
$returnurl = new moodle_url($url, $filter === '' ? [] : ['filter' => $filter]);
$quizzes = updater::get_quizzes($course);
$submitted = [];
$original = [];
$errors = [];

if ($data = data_submitted()) {
    require_sesskey();
    // Nested arrays: q[quizid][field] (new value) and o[quizid][field] (value originally shown).
    foreach (['q' => &$submitted, 'o' => &$original] as $key => &$target) {
        foreach ((array) ($data->$key ?? []) as $quizid => $fields) {
            $quizid = clean_param($quizid, PARAM_INT);
            foreach ((array) $fields as $field => $value) {
                if (in_array($field, updater::FIELDS, true)) {
                    $target[$quizid][$field] = clean_param($value, PARAM_RAW_TRIMMED);
                }
            }
        }
    }
    unset($target);

    [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $original);
    if (!$errors) {
        if (!$changes) {
            redirect($returnurl, get_string('nochanges', 'local_quizbulkedit'), null, \core\output\notification::NOTIFY_INFO);
        }
        $count = updater::apply($course, $changes);
        redirect(
            $returnurl,
            get_string('changessaved', 'local_quizbulkedit', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkeditquizzes', 'local_quizbulkedit'));
if ($errors) {
    echo $OUTPUT->notification(get_string('fixerrors', 'local_quizbulkedit'), 'error');
}
echo $OUTPUT->render(new editor($course->id, $quizzes, $submitted, $original, $errors, $filter));
echo $OUTPUT->footer();
