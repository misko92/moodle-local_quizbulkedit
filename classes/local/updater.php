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
use core_courseformat\formatactions;
use grade_item;
use core_date;
use DateTime;
use mod_quiz\question\display_options;
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
    /** @var string[] The review option bit fields (one bit per review time, see display_options). */
    public const REVIEWFIELDS = [
        'reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks', 'reviewspecificfeedback',
        'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
    ];

    /**
     * @var array Table columns by group, in display order. 'review' is one column for all REVIEWFIELDS.
     *
     * Groups follow the quiz settings form, except that the extra restrictions
     * (password, network address) come first, as they are the most used here.
     */
    public const COLUMNGROUPS = [
        'general' => ['name', 'visible'],
        'restrictions' => ['password', 'subnet', 'seb', 'delay1', 'delay2'],
        'timing' => ['timeopen', 'timeclose', 'timelimit', 'overduehandling', 'graceperiod'],
        'grade' => ['grade', 'gradepass', 'attempts', 'grademethod'],
        'layout' => ['navmethod'],
        'behaviour' => ['shuffleanswers', 'canredoquestions', 'attemptonlast'],
        'review' => ['review'],
        'appearance' => ['showuserpicture', 'decimalpoints', 'questiondecimalpoints', 'showblocks'],
    ];

    /** @var string[] Columns shown until the user picks their own. */
    public const DEFAULTCOLUMNS = [
        'visible', 'password', 'subnet', 'seb', 'timeopen', 'timeclose', 'timelimit', 'attempts', 'grademethod', 'review',
    ];

    /** @var string[] Fields stored in seconds and edited in minutes. */
    public const MINUTEFIELDS = ['timelimit', 'graceperiod', 'delay1', 'delay2'];

    /** @var string[] Editable columns of the quiz table. */
    public const QUIZFIELDS = [
        'password', 'subnet', 'delay1', 'delay2', 'timeopen', 'timeclose', 'timelimit', 'overduehandling', 'graceperiod',
        'attempts', 'grademethod', 'navmethod', 'shuffleanswers', 'canredoquestions', 'attemptonlast',
        'showuserpicture', 'decimalpoints', 'questiondecimalpoints', 'showblocks', ...self::REVIEWFIELDS,
    ];

    /** @var string[] Fields saved through their own core API rather than written to the quiz table. */
    public const SPECIALFIELDS = ['name', 'visible', 'grade', 'gradepass', 'seb'];

    /** @var string[] All editable fields. */
    public const FIELDS = [...self::SPECIALFIELDS, ...self::QUIZFIELDS];

    /** @var string[] Fields holding decimal numbers. */
    protected const NUMBERFIELDS = ['grade', 'gradepass'];

    /**
     * The table columns, in display order.
     *
     * @return string[]
     */
    public static function columns(): array {
        return array_merge(...array_values(self::column_groups()));
    }

    /**
     * The table columns by group, leaving out those not available on this site.
     *
     * @return array group => column names
     */
    public static function column_groups(): array {
        $groups = self::COLUMNGROUPS;
        if (!seb::available()) {
            $groups['restrictions'] = array_values(array_diff($groups['restrictions'], [seb::FIELD]));
        }
        return $groups;
    }

    /** @var int All review time bits. */
    protected const REVIEWBITS = display_options::DURING | display_options::IMMEDIATELY_AFTER |
        display_options::LATER_WHILE_OPEN | display_options::AFTER_CLOSE;

    /** @var string[] Review options that need "The attempt" at the same time (except during the attempt). */
    protected const NEEDSATTEMPT = [
        'reviewcorrectness', 'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
    ];

    /**
     * The quizzes in a course the current user may edit, in course order.
     *
     * Each quiz record also carries visible, from the course module.
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
            'id, course, name, grade, preferredbehaviour, ' . implode(', ', self::QUIZFIELDS)
        );
        $gradepasses = $DB->get_records_menu('grade_items', [
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'itemnumber' => 0,
        ], '', 'iteminstance, gradepass');

        // Order by position on the course page.
        $sequence = array_flip(array_keys($modinfo->get_cms()));
        uasort($cms, fn(cm_info $a, cm_info $b) => $sequence[$a->id] <=> $sequence[$b->id]);

        $quizzes = [];
        foreach ($cms as $quizid => $cm) {
            if (isset($quizrecords[$quizid])) {
                $quiz = $quizrecords[$quizid];
                $quiz->visible = (int) $cm->visible;
                $quiz->grade = (float) $quiz->grade;
                $quiz->hasgradeitem = isset($gradepasses[$quizid]);
                $quiz->gradepass = (float) ($gradepasses[$quizid] ?? 0);
                $quiz->completionpassgrade = !empty($cm->completionpassgrade);
                $quizzes[$quizid] = ['cm' => $cm, 'quiz' => $quiz];
            }
        }
        seb::load($quizzes);
        return $quizzes;
    }

    /**
     * The allowed values of a drop-down field.
     *
     * @param string $field
     * @return array|null value => label, or null if the field is free input
     */
    public static function choices(string $field): ?array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $yesno = [0 => get_string('no'), 1 => get_string('yes')];
        switch ($field) {
            case 'visible':
                return [
                    1 => get_string('visible_show', 'local_quizbulkedit'),
                    0 => get_string('visible_hide', 'local_quizbulkedit'),
                ];
            case 'grademethod':
                return quiz_get_grading_options();
            case 'overduehandling':
                return quiz_get_overdue_handling_options();
            case 'navmethod':
                return quiz_get_navigation_options();
            case 'showuserpicture':
                return quiz_get_user_image_options();
            case 'decimalpoints':
                return range(0, QUIZ_MAX_DECIMAL_OPTION);
            case 'questiondecimalpoints':
                return [-1 => get_string('sameasoverall', 'quiz')] + range(0, QUIZ_MAX_Q_DECIMAL_OPTION);
            case 'shuffleanswers':
            case 'canredoquestions':
            case 'attemptonlast':
            case 'showblocks':
                return $yesno;
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
            case 'grade':
            case 'gradepass':
                // Plain number without trailing zeros, e.g. 10 or 7.5.
                return (string) (0 + (float) $value);
            case 'name':
            case 'password':
            case 'subnet':
            case 'overduehandling':
            case 'navmethod':
                return (string) $value;
            default:
                if (in_array($field, self::MINUTEFIELDS)) {
                    return (string) round($value / MINSECS, 2);
                }
                return (string) (int) $value;
        }
    }

    /**
     * Parse a submitted input string into a DB value.
     *
     * @param string $field
     * @param string $input
     * @return mixed the DB value, or null if the input is invalid
     */
    public static function parse_value(string $field, string $input) {
        $input = trim($input);
        if (in_array($field, self::REVIEWFIELDS)) {
            if (!preg_match('/^\d+$/', $input) || ((int) $input & ~self::REVIEWBITS)) {
                return null;
            }
            return (int) $input;
        }
        $choices = self::choices($field);
        if ($choices !== null) {
            // Return the key itself, so it keeps its type (int, or string like 'autosubmit').
            foreach (array_keys($choices) as $key) {
                if ((string) $key === $input) {
                    return $key;
                }
            }
            return null;
        }
        if (in_array($field, self::MINUTEFIELDS)) {
            if ($input === '') {
                return 0;
            }
            if (!is_numeric($input) || $input < 0) {
                return null;
            }
            return (int) round($input * MINSECS);
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
            case 'name':
                $name = clean_param($input, PARAM_TEXT);
                return ($name === '' || \core_text::strlen($name) > 255) ? null : $name;
            case 'grade':
            case 'gradepass':
                if ($input === '' && $field === 'gradepass') {
                    return 0.0;
                }
                if (!is_numeric($input) || $input < 0) {
                    return null;
                }
                return (float) $input;
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
            case 'subnet':
                // Comma-separated addresses in any address_in_subnet() form (full, partial, range, CIDR; IPv4 or IPv6).
                // Only catch obvious typos here; the quiz settings form itself does no checking at all.
                $parts = array_map('trim', explode(',', $input));
                $bad = array_filter($parts, fn($part) => !preg_match('~^[0-9a-f:./-]+$~i', $part));
                if ($input !== '' && ($bad || \core_text::strlen($input) > 255)) {
                    return null;
                }
                return implode(', ', array_filter($parts, 'strlen'));
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
                if ($field === seb::FIELD) {
                    [$value, $error] = seb::validate($quizid, $input, $quizzes);
                    if ($error !== null) {
                        $errors[$quizid][$field] = $error;
                    } else if ($value !== (string) $quiz->seb) {
                        $changes[$quizid][$field] = $value;
                    }
                    continue;
                }
                $value = self::parse_value($field, $input);
                if ($value === null) {
                    $errors[$quizid][$field] = get_string(self::error_string($field), 'local_quizbulkedit');
                    continue;
                }
                if ($field === 'visible' && !has_capability('moodle/course:activityvisibility', $cm->context)) {
                    $errors[$quizid][$field] = get_string('errorvisibility', 'local_quizbulkedit');
                    continue;
                }
                $changed = in_array($field, self::NUMBERFIELDS) ?
                    abs($value - $quiz->$field) > 1e-7 : (string) $value !== (string) $quiz->$field;
                if ($changed) {
                    $changes[$quizid][$field] = $value;
                }
            }

            if (array_intersect_key($changes[$quizid] ?? [], array_flip(self::REVIEWFIELDS))) {
                $review = [];
                foreach (self::REVIEWFIELDS as $field) {
                    $review[$field] = $changes[$quizid][$field] ?? (int) $quiz->$field;
                }
                foreach (self::normalise_review($review, $quiz->preferredbehaviour) as $field => $value) {
                    if ($value != $quiz->$field) {
                        $changes[$quizid][$field] = $value;
                    } else {
                        unset($changes[$quizid][$field]);
                    }
                }
                if (empty($changes[$quizid])) {
                    unset($changes[$quizid]);
                }
            }

            if (isset($changes[$quizid]['grade']) || isset($changes[$quizid]['gradepass'])) {
                // As the quiz settings form.
                $grade = $changes[$quizid]['grade'] ?? $quiz->grade;
                $gradepass = $changes[$quizid]['gradepass'] ?? $quiz->gradepass;
                $field = isset($changes[$quizid]['gradepass']) ? 'gradepass' : 'grade';
                $cbm = in_array($quiz->preferredbehaviour, ['deferredcbm', 'immediatecbm']);
                if (isset($changes[$quizid]['gradepass']) && !$quiz->hasgradeitem) {
                    $errors[$quizid]['gradepass'] = get_string('errornogradeitem', 'local_quizbulkedit');
                } else if ($grade > 0 && $gradepass > $grade && !$cbm) {
                    $errors[$quizid][$field] = get_string('gradepassgreaterthangrade', 'grades', $grade);
                } else if ($quiz->completionpassgrade && $gradepass == 0) {
                    $errors[$quizid][$field] = get_string('activitygradetopassnotset', 'completion');
                }
            }

            if (isset($changes[$quizid]['overduehandling']) || isset($changes[$quizid]['graceperiod'])) {
                // As the quiz settings form: a grace period must be longer than the site minimum.
                $handling = $changes[$quizid]['overduehandling'] ?? $quiz->overduehandling;
                $grace = $changes[$quizid]['graceperiod'] ?? $quiz->graceperiod;
                $min = (int) get_config('quiz', 'graceperiodmin');
                if ($handling === 'graceperiod' && $grace <= $min && empty($errors[$quizid]['graceperiod'])) {
                    $errors[$quizid]['graceperiod'] = get_string('graceperiodtoosmall', 'quiz', format_time($min));
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
     * Apply the same review option rules as the quiz settings form.
     *
     * The form greys out (and so saves as off) options that make no sense:
     * marks without max marks, details without the attempt itself, and during-
     * the-attempt options the question behaviour doesn't use. It also always
     * shows the attempt during the attempt and never the overall feedback.
     *
     * @param array $review review field => bits
     * @param string $behaviour the quiz's preferred behaviour
     * @return array review field => bits
     */
    public static function normalise_review(array $review, string $behaviour): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $review['reviewattempt'] |= display_options::DURING;
        $review['reviewoverallfeedback'] &= ~display_options::DURING;
        try {
            $unused = \question_engine::get_behaviour_unused_display_options($behaviour);
        } catch (\Throwable $e) {
            $unused = [];
        }
        foreach ($unused as $option) {
            if (isset($review['review' . $option])) {
                $review['review' . $option] &= ~display_options::DURING;
            }
        }
        $review['reviewmarks'] &= $review['reviewmaxmarks'];
        $afterattempt = $review['reviewattempt'] | display_options::DURING;
        foreach (self::NEEDSATTEMPT as $field) {
            $review[$field] &= $afterattempt;
        }
        return $review;
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
        if (in_array($field, self::REVIEWFIELDS)) {
            return 'errorreview';
        }
        if (in_array($field, self::MINUTEFIELDS)) {
            return 'errorminutes';
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
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

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

            if (isset($fields['name'])) {
                formatactions::cm($course->id)->rename($cm->id, $fields['name']);
            }
            if (isset($fields['grade'])) {
                // Rescales existing grades and overall feedback, and updates the gradebook.
                quiz_settings::create($quiz->id)->get_grade_calculator()->update_quiz_maximum_grade($fields['grade']);
            }
            if (isset($fields['gradepass'])) {
                $gradeitem = grade_item::fetch([
                    'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz',
                    'iteminstance' => $quiz->id, 'itemnumber' => 0,
                ]);
                if ($gradeitem) {
                    $gradeitem->gradepass = $fields['gradepass'];
                    $gradeitem->update('local_quizbulkedit');
                }
            }
            if (isset($fields['seb'])) {
                seb::apply($quiz, $cm, $fields['seb']);
            }
            if (isset($fields['visible']) && $fields['visible'] != $cm->visible) {
                require_capability('moodle/course:activityvisibility', $context);
                set_coursemodule_visible($cm->id, $fields['visible'], 1, false);
            }
            if ($old->timeopen != $quiz->timeopen || $old->timeclose != $quiz->timeclose) {
                quiz_update_events($quiz);
            }
            if (
                $old->timelimit != $quiz->timelimit || $old->timeclose != $quiz->timeclose ||
                $old->graceperiod != $quiz->graceperiod || $old->overduehandling != $quiz->overduehandling
            ) {
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
