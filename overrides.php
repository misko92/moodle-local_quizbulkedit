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
 * Add, update or remove quiz overrides (e.g. extra time) for students or groups, on many quizzes at once.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_quizbulkedit\form\overrides_form;
use local_quizbulkedit\local\overrides;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/local/quizbulkedit/overrides.php', ['id' => $course->id]);
$backurl = new moodle_url('/local/quizbulkedit/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('overrides', 'local_quizbulkedit'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('bulkeditquizzes', 'local_quizbulkedit'), $backurl);
$PAGE->navbar->add(get_string('overrides', 'local_quizbulkedit'), $url);

$quizzes = overrides::get_quizzes($course);

// Students and groups this user may give overrides to.
$allgroups = has_capability('moodle/site:accessallgroups', $context);
$mygroups = $allgroups ? null : array_keys(groups_get_all_groups($course->id, $USER->id));
$users = [];
foreach (get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true) as $user) {
    if ($allgroups || array_intersect($mygroups, array_keys(groups_get_all_groups($course->id, $user->id)))) {
        $users[$user->id] = fullname($user);
    }
}
$groups = [];
foreach (groups_get_all_groups($course->id) as $group) {
    if ($allgroups || in_array($group->id, $mygroups)) {
        $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
    }
}
$quiznames = array_map(fn($entry) => $entry['cm']->get_formatted_name(), $quizzes);

$customdata = [
    'courseid' => $course->id,
    'users' => $users,
    'groups' => $groups,
    'quizzes' => $quiznames,
    // Offer Apply once a preview has been shown, i.e. on any submission of the form.
    'canapply' => optional_param('previewbutton', '', PARAM_RAW) !== '' || optional_param('applybutton', '', PARAM_RAW) !== '',
];
$form = new overrides_form($url, $customdata);

if ($form->is_cancelled()) {
    redirect($backurl);
}

$plan = null;
if ($data = $form->get_data()) {
    $chosen = !empty($data->allquizzes) ? $quizzes : array_intersect_key($quizzes, array_flip($data->quizzes ?? []));
    $targets = [];
    foreach ($data->users ?? [] as $userid) {
        if (isset($users[$userid])) {
            $targets[] = ['userid' => (int) $userid, 'name' => $users[$userid]];
        }
    }
    foreach ($data->groups ?? [] as $groupid) {
        if (isset($groups[$groupid])) {
            $targets[] = ['groupid' => (int) $groupid, 'name' => $groups[$groupid]];
        }
    }
    $plan = overrides::plan($chosen, $targets, $data);

    if (!empty($data->applybutton)) {
        [$saved, $removed] = overrides::apply($quizzes, $plan);
        redirect(
            $url,
            get_string('ovdone', 'local_quizbulkedit', (object) ['saved' => $saved, 'removed' => $removed]),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides', 'local_quizbulkedit'));
echo html_writer::tag('p', get_string('overrides_desc', 'local_quizbulkedit'));
echo html_writer::link($backurl, get_string('backtobulkedit', 'local_quizbulkedit'), ['class' => 'd-inline-block mb-3']);

if (!$quizzes) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_quizbulkedit'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($plan !== null) {
    $todo = array_filter($plan, fn($row) => $row['action'] !== 'skip');
    echo $OUTPUT->heading(get_string('ovpreviewheading', 'local_quizbulkedit'), 3);
    echo $OUTPUT->notification(get_string(
        'ovpreviewsummary',
        'local_quizbulkedit',
        (object) ['todo' => count($todo), 'skipped' => count($plan) - count($todo)]
    ), 'info', false);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $table->head = array_map(
        fn($key) => get_string($key, 'local_quizbulkedit'),
        ['ovcolquiz', 'ovcolwho', 'ovcolaction', 'ovcolbefore', 'ovcolafter', 'ovcolnote']
    );
    foreach ($plan as $row) {
        $table->data[] = [
            $quiznames[$row['quizid']],
            s($row['target']['name']),
            get_string('ovdo' . $row['action'], 'local_quizbulkedit'),
            overrides::describe($row['existing']),
            $row['action'] === 'delete' ? '–' : ($row['values'] === null ? '' : overrides::describe($row['values'])),
            s($row['note']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}

$form->display();

// Current overrides in the course, so it's easy to see who has what.
[$insql, $params] = $DB->get_in_or_equal(array_keys($quizzes) ?: [0]);
$current = $DB->get_records_select('quiz_overrides', "quiz $insql", $params, 'quiz, groupid, userid');
echo $OUTPUT->heading(get_string('ovcurrent', 'local_quizbulkedit'), 3, 'mt-4');
if (!$current) {
    echo html_writer::tag('p', get_string('ovcurrentnone', 'local_quizbulkedit'), ['class' => 'text-muted']);
} else {
    $userids = array_filter(array_column($current, 'userid'));
    $names = $userids ? array_map('fullname', $DB->get_records_list('user', 'id', array_unique($userids))) : [];
    $allgroupnames = array_map(fn($group) => format_string($group->name), groups_get_all_groups($course->id));
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $table->head = array_map(
        fn($key) => get_string($key, 'local_quizbulkedit'),
        ['ovcolquiz', 'ovcolwho', 'ovcolsettings', 'ovreason']
    );
    foreach ($current as $override) {
        $who = $override->userid ? ($names[$override->userid] ?? '?') :
            get_string('ovgroup', 'local_quizbulkedit', $allgroupnames[$override->groupid] ?? '?');
        $link = new moodle_url('/mod/quiz/overrides.php', [
            'cmid' => $quizzes[$override->quiz]['cm']->id, 'mode' => $override->userid ? 'user' : 'group',
        ]);
        $table->data[] = [
            html_writer::link($link, $quiznames[$override->quiz]),
            s($who),
            overrides::describe($override),
            s($override->reason ?? ''),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}

echo $OUTPUT->footer();
