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

use context_module;
use mod_quiz\local\override_manager;
use stdClass;

/**
 * Plan and save user/group overrides (e.g. extra time) on many quizzes at once.
 *
 * Saving goes through mod_quiz's override_manager, which validates, purges the
 * override cache, updates calendar events and attempts in progress, and logs.
 * Its save replaces all of an override's settings, so new values are merged
 * into any existing override for the same student or group first.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overrides {
    /** @var string[] Settings an override can change. */
    public const SETTINGS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /**
     * The quizzes in a course whose overrides the current user may manage, in course order.
     *
     * @param stdClass $course
     * @return array quiz id => ['cm' => cm_info, 'quiz' => stdClass (full quiz record, with cmid)]
     */
    public static function get_quizzes(stdClass $course): array {
        global $DB;
        $quizzes = [];
        $cms = array_filter(
            get_fast_modinfo($course)->get_instances_of('quiz'),
            fn($cm) => has_capability('mod/quiz:manageoverrides', $cm->context)
        );
        if (!$cms) {
            return [];
        }
        $records = $DB->get_records_list('quiz', 'id', array_keys($cms));
        $order = array_flip(array_keys(get_fast_modinfo($course)->get_cms()));
        uasort($cms, fn($a, $b) => $order[$a->id] <=> $order[$b->id]);
        foreach ($cms as $quizid => $cm) {
            $records[$quizid]->cmid = $cm->id;
            $quizzes[$quizid] = ['cm' => $cm, 'quiz' => $records[$quizid]];
        }
        return $quizzes;
    }

    /**
     * Work out what to do for each student/group and quiz.
     *
     * @param array $quizzes the chosen quizzes, from get_quizzes()
     * @param array $targets list of ['userid' => id] or ['groupid' => id], each with a 'name'
     * @param stdClass $data form data (action, *mode fields and values, reason)
     * @return array rows of [quizid, target, existing (record|null), values (array|null), action, note]
     *     where action is 'create', 'update', 'delete' or 'skip'
     */
    public static function plan(array $quizzes, array $targets, stdClass $data): array {
        global $DB;
        $rows = [];
        foreach ($quizzes as $quizid => ['quiz' => $quiz]) {
            foreach ($targets as $target) {
                $who = isset($target['userid']) ? ['userid' => $target['userid']] : ['groupid' => $target['groupid']];
                $existing = $DB->get_record('quiz_overrides', ['quiz' => $quizid] + $who) ?: null;
                $row = ['quizid' => $quizid, 'target' => $target, 'existing' => $existing, 'values' => null, 'note' => ''];

                if ($data->action === 'delete') {
                    $row['action'] = $existing ? 'delete' : 'skip';
                    $row['note'] = $existing ? '' : get_string('ovnooverride', 'local_quizbulkedit');
                    $rows[] = $row;
                    continue;
                }

                [$values, $notes] = self::new_values($quiz, $existing, $data);
                // The override manager drops values equal to the quiz's own settings.
                // The reason is only a note, so on its own it doesn't call for an override.
                $effective = array_filter(
                    array_intersect_key($values, array_flip(self::SETTINGS)),
                    fn($value, $key) => $value !== null && $value != $quiz->$key,
                    ARRAY_FILTER_USE_BOTH
                );
                $unchanged = $existing && !array_filter(
                    self::SETTINGS,
                    fn($key) => (string) ($existing->$key ?? '') !== (string) ($values[$key] ?? '')
                );
                $reasonchanged = $existing && isset($values['reason']) && $values['reason'] !== (string) $existing->reason;

                if (!$effective) {
                    $row['action'] = 'skip';
                    // A more specific note (e.g. no time limit to change) already explains it.
                    if (!$notes) {
                        $notes[] = get_string('ovsameasquiz', 'local_quizbulkedit');
                    }
                } else if ($unchanged && !$reasonchanged) {
                    $row['action'] = 'skip';
                    $notes[] = get_string('ovnochange', 'local_quizbulkedit');
                } else {
                    $open = $values['timeopen'] ?? $quiz->timeopen;
                    $close = $values['timeclose'] ?? $quiz->timeclose;
                    if ($open && $close && $close <= $open) {
                        $row['action'] = 'skip';
                        $notes[] = get_string('closebeforeopen', 'quiz');
                    } else {
                        $row['action'] = $existing ? 'update' : 'create';
                        $row['values'] = $values;
                    }
                }
                $row['note'] = implode(' ', $notes);
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * The override settings after applying the form's changes on top of any existing override.
     *
     * @param stdClass $quiz
     * @param stdClass|null $existing existing override
     * @param stdClass $data form data
     * @return array [settings => value|null (plus reason when given), notes]
     */
    protected static function new_values(stdClass $quiz, ?stdClass $existing, stdClass $data): array {
        $values = [];
        foreach (self::SETTINGS as $key) {
            $values[$key] = $existing->$key ?? null;
        }
        $notes = [];

        switch ($data->timelimitmode) {
            case 'multiply':
            case 'add':
                if (!$quiz->timelimit) {
                    $notes[] = get_string('ovnotimelimit', 'local_quizbulkedit');
                    break;
                }
                $values['timelimit'] = $data->timelimitmode === 'multiply' ?
                    (int) round($quiz->timelimit * $data->timelimitfactor) :
                    (int) ($quiz->timelimit + round($data->timelimitminutes * MINSECS));
                break;
            case 'set':
                $values['timelimit'] = (int) round($data->timelimitminutes * MINSECS);
                break;
        }
        if ($data->attemptsmode === 'set') {
            $values['attempts'] = (int) $data->attempts;
        }
        if ($data->timeopenmode === 'set') {
            $values['timeopen'] = (int) $data->timeopen;
        }
        switch ($data->timeclosemode) {
            case 'set':
                $values['timeclose'] = (int) $data->timeclose;
                break;
            case 'add':
                if (!$quiz->timeclose) {
                    $notes[] = get_string('ovnoclose', 'local_quizbulkedit');
                    break;
                }
                $values['timeclose'] = (int) ($quiz->timeclose + round($data->timecloseminutes * MINSECS));
                break;
        }
        if ($data->passwordmode === 'set') {
            $values['password'] = $data->password;
        }
        if (trim($data->reason ?? '') !== '') {
            $values['reason'] = trim($data->reason);
        }
        return [$values, $notes];
    }

    /**
     * Carry out a plan.
     *
     * @param array $quizzes from get_quizzes()
     * @param array $rows from plan()
     * @return array [saved count, removed count]
     */
    public static function apply(array $quizzes, array $rows): array {
        global $DB;
        $saved = 0;
        $removed = 0;
        // All or nothing.
        $transaction = $DB->start_delegated_transaction();
        foreach ($rows as $row) {
            if ($row['action'] === 'skip') {
                continue;
            }
            $quiz = $quizzes[$row['quizid']]['quiz'];
            $manager = new override_manager($quiz, context_module::instance($quiz->cmid));
            $manager->require_manage_capability();
            if ($row['action'] === 'delete') {
                $manager->delete_overrides([$row['existing']]);
                $removed++;
                continue;
            }
            $formdata = $row['values'] + $row['target'];
            unset($formdata['name']);
            if (isset($formdata['reason'])) {
                $formdata['reasonformat'] = FORMAT_PLAIN;
            }
            if ($row['existing']) {
                $formdata['id'] = $row['existing']->id;
            }
            $manager->save_override($formdata);
            $saved++;
        }
        $transaction->allow_commit();
        return [$saved, $removed];
    }

    /**
     * A one-line description of override settings, e.g. "Time limit: 1 hour 30 mins; Attempts: 2".
     *
     * @param array|stdClass|null $values settings, null values meaning "as the quiz"
     * @return string
     */
    public static function describe($values): string {
        $values = (array) $values;
        $parts = [];
        foreach (self::SETTINGS as $key) {
            if (!isset($values[$key])) {
                continue;
            }
            $value = $values[$key];
            switch ($key) {
                case 'timeopen':
                case 'timeclose':
                    $shown = $value ? userdate($value) : get_string('ovnone', 'local_quizbulkedit');
                    break;
                case 'timelimit':
                    $shown = $value ? format_time($value) : get_string('ovnone', 'local_quizbulkedit');
                    break;
                case 'attempts':
                    $shown = $value ? $value : get_string('unlimited');
                    break;
                default:
                    $shown = s($value);
            }
            $parts[] = get_string('ov' . $key, 'local_quizbulkedit') . ': ' . $shown;
        }
        return $parts ? implode('; ', $parts) : '–';
    }
}
