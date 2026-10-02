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

use cm_info;
use context_module;
use core_date;
use DateTime;
use mod_quiz\access_manager;
use mod_quiz\quiz_settings;
use stdClass;

/**
 * Reads, validates and saves the quiz settings edited on the bulk page.
 *
 * Settings are written straight to the quiz table rather than through
 * quiz_update_instance(), which expects the full mod_form submission and would
 * reset review options and overall feedback if given a partial record. The
 * side effects quiz_update_instance() would have for these fields (calendar
 * events, open attempt deadlines, regrading, preview cleanup, course cache,
 * log event) are reproduced here.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updater {
    /** @var string[] Editable columns of the quiz table. */
    public const QUIZFIELDS = [
        'password', 'timeopen', 'timeclose', 'timelimit', 'attempts', 'grademethod', 'browsersecurity',
    ];

    /** @var string[] All editable fields, in display order. visible is the course module's; reviewfrom is virtual. */
    public const FIELDS = [
        'visible', 'password', 'timeopen', 'timeclose', 'timelimit', 'attempts', 'grademethod', 'browsersecurity',
        'reviewfrom',
    ];

    /** @var string[] The review option bit fields copied by reviewfrom. */
    public const REVIEWFIELDS = [
        'reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks', 'reviewspecificfeedback',
        'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
    ];

    /**
     * The fields worth showing on this site.
     *
     * Browser security is left out when no access rule offers a choice besides "None".
     *
     * @return string[]
     */
    public static function get_fields(): array {
        return array_values(array_filter(
            self::FIELDS,
            fn($field) => $field !== 'browsersecurity' || count(self::choices($field)) > 1
        ));
    }

    /**
     * The quizzes in a course the current user may edit, in course order.
     *
     * Each quiz record also carries the virtual fields visible (from the course
     * module) and reviewfrom (always '').
     *
     * @param stdClass $course
     * @return array quiz id => ['cm' => cm_info, 'quiz' => stdClass]
     */
    public static function get_quizzes(stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $cms = [];
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if (has_capability('moodle/course:manageactivities', $cm->context)) {
                $cms[$cm->instance] = $cm;
            }
        }
        if (!$cms) {
            return [];
        }
        $quizrecords = $DB->get_records_list(
            'quiz',
            'id',
            array_keys($cms),
            '',
            'id, course, name, ' . implode(', ', array_merge(self::QUIZFIELDS, self::REVIEWFIELDS))
        );

        // Order by position on the course page.
        $sequence = array_flip(array_keys($modinfo->get_cms()));
        uasort($cms, fn(cm_info $a, cm_info $b) => $sequence[$a->id] <=> $sequence[$b->id]);

        $quizzes = [];
        foreach ($cms as $quizid => $cm) {
            if (isset($quizrecords[$quizid])) {
                $quiz = $quizrecords[$quizid];
                $quiz->visible = (int) $cm->visible;
                $quiz->reviewfrom = '';
                $quizzes[$quizid] = ['cm' => $cm, 'quiz' => $quiz];
            }
        }
        return $quizzes;
    }

    /**
     * The allowed values of a drop-down field.
     *
     * @param string $field
     * @param array $quizzes from get_quizzes(), needed for reviewfrom
     * @return array|null value => label, or null if the field is free input
     */
    public static function choices(string $field, array $quizzes = []): ?array {
        global $CFG;
        switch ($field) {
            case 'visible':
                return [
                    1 => get_string('visible_show', 'local_quizbulkedit'),
                    0 => get_string('visible_hide', 'local_quizbulkedit'),
                ];
            case 'grademethod':
                require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                return quiz_get_grading_options();
            case 'browsersecurity':
                return access_manager::get_browser_security_choices();
            case 'reviewfrom':
                $choices = ['' => get_string('reviewkeep', 'local_quizbulkedit')];
                foreach ($quizzes as $quizid => ['cm' => $cm]) {
                    $choices[$quizid] = get_string('reviewcopy', 'local_quizbulkedit', $cm->get_formatted_name());
                }
                return $choices;
        }
        return null;
    }

    /**
     * Format a stored field value as the string shown in the form input.
     *
     * @param string $field
     * @param mixed $value the DB value
     * @return string
     */
    public static function format_value(string $field, $value): string {
        switch ($field) {
            case 'timeopen':
            case 'timeclose':
                if (empty($value)) {
                    return '';
                }
                $dt = new DateTime('@' . $value);
                $dt->setTimezone(core_date::get_user_timezone_object());
                return $dt->format('Y-m-d\TH:i');
            case 'timelimit':
                return (string) round($value / MINSECS, 2);
            case 'attempts':
            case 'grademethod':
            case 'visible':
                return (string) (int) $value;
            default:
                return (string) $value;
        }
    }

    /**
     * Parse a submitted input string into a DB value.
     *
     * @param string $field
     * @param string $input
     * @param array $quizzes from get_quizzes(), needed for reviewfrom
     * @return mixed the DB value, or null if the input is invalid
     */
    public static function parse_value(string $field, string $input, array $quizzes = []) {
        $input = trim($input);
        $choices = self::choices($field, $quizzes);
        if ($choices !== null) {
            if ($input === '' || !array_key_exists($input, $choices)) {
                return null;
            }
            return $field === 'browsersecurity' ? $input : (int) $input;
        }
        switch ($field) {
            case 'timeopen':
            case 'timeclose':
                if ($input === '') {
                    return 0;
                }
                $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $input, core_date::get_user_timezone_object());
                if (!$dt || $dt->format('Y-m-d\TH:i') !== $input) {
                    return null;
                }
                return $dt->getTimestamp();
            case 'timelimit':
                if ($input === '') {
                    return 0;
                }
                if (!is_numeric($input) || $input < 0) {
                    return null;
                }
                return (int) round($input * MINSECS);
            case 'attempts':
                if ($input === '') {
                    return 0;
                }
                if (!preg_match('/^\d+$/', $input)) {
                    return null;
                }
                return (int) $input;
            case 'password':
                return \core_text::strlen($input) > 255 ? null : $input;
        }
        return null;
    }

    /**
     * Work out which fields changed and validate them.
     *
     * A field counts as changed only when the submitted string differs from
     * the string originally sent to the browser, so untouched values are never
     * rewritten (and never clobber edits made elsewhere in the meantime).
     *
     * @param array $quizzes from get_quizzes()
     * @param array $submitted quiz id => field => submitted string
     * @param array $original quiz id => field => string originally shown
     * @return array [changes (quiz id => field => DB value), errors (quiz id => field => message)]
     */
    public static function collect_changes(array $quizzes, array $submitted, array $original): array {
        $changes = [];
        $errors = [];
        foreach ($submitted as $quizid => $fields) {
            if (!isset($quizzes[$quizid]) || !is_array($fields)) {
                continue;
            }
            ['cm' => $cm, 'quiz' => $quiz] = $quizzes[$quizid];
            foreach (self::FIELDS as $field) {
                if (!isset($fields[$field])) {
                    continue;
                }
                $input = (string) $fields[$field];
                $orig = (string) ($original[$quizid][$field] ?? self::format_value($field, $quiz->$field));
                if ($input === $orig) {
                    continue;
                }
                $value = self::parse_value($field, $input, $quizzes);
                if ($value === null) {
                    $errors[$quizid][$field] = get_string(self::error_string($field), 'local_quizbulkedit');
                    continue;
                }
                if ($field === 'reviewfrom' && $value == $quizid) {
                    continue;
                }
                if ($field === 'visible' && !has_capability('moodle/course:activityvisibility', $cm->context)) {
                    $errors[$quizid][$field] = get_string('errorvisibility', 'local_quizbulkedit');
                    continue;
                }
                if ((string) $value !== (string) $quiz->$field) {
                    $changes[$quizid][$field] = $value;
                }
            }

            if (isset($changes[$quizid]['timeopen']) || isset($changes[$quizid]['timeclose'])) {
                $open = $changes[$quizid]['timeopen'] ?? $quiz->timeopen;
                $close = $changes[$quizid]['timeclose'] ?? $quiz->timeclose;
                if ($open && $close && $close <= $open && empty($errors[$quizid]['timeclose'])) {
                    $errors[$quizid]['timeclose'] = get_string('errorcloseopen', 'local_quizbulkedit');
                }
            }
        }
        return [$changes, $errors];
    }

    /**
     * The lang string shown when a field's value can't be parsed.
     *
     * @param string $field
     * @return string string identifier
     */
    private static function error_string(string $field): string {
        if (in_array($field, ['timeopen', 'timeclose'])) {
            return 'errordate';
        }
        if (self::choices($field) !== null) {
            return 'errorchoice';
        }
        return 'error' . $field;
    }

    /**
     * Save changes to the quizzes of one course.
     *
     * @param stdClass $course
     * @param array $changes quiz id => field => DB value, from collect_changes()
     * @return int number of quizzes updated
     */
    public static function apply(stdClass $course, array $changes): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        // Review options are copied from the sources as they were before this save.
        $sourceids = array_filter(array_column($changes, 'reviewfrom'));
        $sources = $sourceids ? $DB->get_records_list(
            'quiz',
            'id',
            array_unique($sourceids),
            '',
            'id, course, ' . implode(', ', self::REVIEWFIELDS)
        ) : [];

        $transaction = $DB->start_delegated_transaction();
        $count = 0;
        foreach ($changes as $quizid => $fields) {
            $fields = array_intersect_key($fields, array_flip(self::FIELDS));
            if (!$fields) {
                continue;
            }
            $quiz = $DB->get_record('quiz', ['id' => $quizid, 'course' => $course->id], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
            $context = context_module::instance($cm->id);
            require_capability('moodle/course:manageactivities', $context);

            $update = array_intersect_key($fields, array_flip(self::QUIZFIELDS));
            if (!empty($fields['reviewfrom'])) {
                $source = $sources[$fields['reviewfrom']] ?? null;
                if (!$source || $source->course != $course->id) {
                    throw new \moodle_exception('invalidrecord', 'error', '', 'quiz');
                }
                foreach (self::REVIEWFIELDS as $field) {
                    $update[$field] = $source->$field;
                }
            }

            $old = clone($quiz);
            if ($update) {
                $update['id'] = $quiz->id;
                $update['timemodified'] = time();
                $DB->update_record('quiz', (object) $update);
                foreach ($update as $field => $value) {
                    $quiz->$field = $value;
                }
            }
            $quiz->coursemodule = $cm->id;
            $quiz->cmid = $cm->id;

            if (isset($fields['visible']) && $fields['visible'] != $cm->visible) {
                require_capability('moodle/course:activityvisibility', $context);
                set_coursemodule_visible($cm->id, $fields['visible'], 1, false);
            }
            if ($old->timeopen != $quiz->timeopen || $old->timeclose != $quiz->timeclose) {
                quiz_update_events($quiz);
            }
            if ($old->timelimit != $quiz->timelimit || $old->timeclose != $quiz->timeclose) {
                quiz_update_open_attempts(['quizid' => $quiz->id]);
            }
            if ($old->grademethod != $quiz->grademethod) {
                quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_all_final_grades();
                quiz_update_grades($quiz);
            }
            quiz_delete_previews($quiz);

            \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();
            $count++;
        }
        $transaction->allow_commit();

        if ($count) {
            rebuild_course_cache($course->id, true);
        }
        return $count;
    }
}
