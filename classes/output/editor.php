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

namespace local_quizbulkedit\output;

use local_quizbulkedit\local\seb;
use local_quizbulkedit\local\updater;
use mod_quiz\question\display_options;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * The bulk quiz settings table.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor implements renderable, templatable {
    /** @var array review bit field => lang string, labelled as in the quiz settings form */
    protected const REVIEWITEMS = [
        'reviewattempt' => ['theattempt', 'quiz'],
        'reviewcorrectness' => ['whethercorrect', 'question'],
        'reviewmaxmarks' => ['maxmarks', 'quiz'],
        'reviewmarks' => ['marks', 'quiz'],
        'reviewspecificfeedback' => ['specificfeedback', 'question'],
        'reviewgeneralfeedback' => ['generalfeedback', 'question'],
        'reviewrightanswer' => ['rightanswer', 'question'],
        'reviewoverallfeedback' => ['reviewoverallfeedback', 'quiz'],
    ];

    /** @var array review timing (quiz lang string suffix) => bit */
    protected const REVIEWTIMES = [
        'during' => display_options::DURING,
        'immediately' => display_options::IMMEDIATELY_AFTER,
        'open' => display_options::LATER_WHILE_OPEN,
        'closed' => display_options::AFTER_CLOSE,
    ];

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param array $quizzes from updater::get_quizzes()
     * @param array $submitted quiz id => field => string, to redisplay after a failed save
     * @param array $original quiz id => field => string originally shown, kept across a failed save
     * @param array $errors quiz id => field => message
     * @param string $filter keyword the quiz list is filtered by
     */
    public function __construct(
        /** @var int course id */
        protected int $courseid,
        /** @var array quizzes */
        protected array $quizzes,
        /** @var array submitted values */
        protected array $submitted = [],
        /** @var array original values */
        protected array $original = [],
        /** @var array validation errors */
        protected array $errors = [],
        /** @var string keyword filter */
        protected string $filter = ''
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $types = [
            'password' => 'text',
            'subnet' => 'text',
            'timeopen' => 'datetime-local',
            'timeclose' => 'datetime-local',
            'attempts' => 'number',
            'name' => 'text',
            'grade' => 'number',
            'gradepass' => 'number',
        ] + array_fill_keys(updater::MINUTEFIELDS, 'number');
        $hints = ['subnet', 'timelimit', 'graceperiod', 'delay1', 'delay2', 'attempts', 'grade', 'review'];
        $columns = updater::columns();
        $shown = array_flip($this->shown_columns());

        $copychoices = [];
        $sebchoices = ['0' => get_string('no')] + seb::turn_on_choices();
        foreach ($this->quizzes as $quizid => ['cm' => $cm, 'quiz' => $quiz]) {
            $copychoices[$quizid] = get_string('reviewcopy', 'local_quizbulkedit', $cm->get_formatted_name());
            if ($quiz->seb) {
                $sebchoices['q' . $quizid] = $copychoices[$quizid] . ' (' . seb::describe($quiz) . ')';
            }
        }
        $toolbarchoices = ['review' => $copychoices, seb::FIELD => $sebchoices];

        $fields = [];
        foreach ($columns as $field) {
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_quizbulkedit'),
                'hint' => in_array($field, $hints) ? get_string($field . '_hint', 'local_quizbulkedit') : null,
                'isreview' => $field === 'review',
                'colhidden' => !isset($shown[$field]),
            ] + $this->control(
                $field,
                $types[$field] ?? null,
                $toolbarchoices[$field] ?? updater::choices($field),
                ''
            );
        }

        $groups = [];
        foreach (updater::column_groups() as $group => $groupcolumns) {
            $groups[] = [
                'label' => get_string('group_' . $group, 'local_quizbulkedit'),
                'columns' => array_map(fn($field) => [
                    'field' => $field,
                    'label' => get_string($field, 'local_quizbulkedit'),
                    'checked' => isset($shown[$field]),
                ], $groupcolumns),
            ];
        }

        $rows = [];
        foreach ($this->quizzes as $quizid => ['cm' => $cm, 'quiz' => $quiz]) {
            $name = $cm->get_formatted_name();
            $values = [];
            $originals = [];
            foreach (updater::FIELDS as $field) {
                $originals[$field] = $this->original[$quizid][$field] ?? updater::format_value($field, $quiz->$field);
                $values[$field] = $this->submitted[$quizid][$field] ?? $originals[$field];
            }
            $reviewchanged = false;
            foreach (updater::REVIEWFIELDS as $field) {
                $reviewchanged = $reviewchanged || $values[$field] !== $originals[$field];
            }
            $reviewerrors = array_intersect_key($this->errors[$quizid] ?? [], array_flip(updater::REVIEWFIELDS));

            $cells = [];
            foreach ($columns as $field) {
                if ($field === 'review') {
                    $cells[] = $this->review_cell($quizid, $name, $values, $originals, $copychoices, $reviewerrors) + [
                        'changed' => $reviewchanged,
                        'colhidden' => !isset($shown[$field]),
                    ];
                    continue;
                }
                if ($field === seb::FIELD) {
                    $cells[] = $this->seb_cell($quizid, $cm, $quiz, $sebchoices, $values[$field], $originals[$field]) + [
                        'field' => $field,
                        'original' => $originals[$field],
                        'changed' => $values[$field] !== $originals[$field],
                        'error' => $this->errors[$quizid][$field] ?? null,
                        'label' => get_string($field, 'local_quizbulkedit') . ': ' . $name,
                        'colhidden' => !isset($shown[$field]),
                    ];
                    continue;
                }
                $cells[] = [
                    'field' => $field,
                    'original' => $originals[$field],
                    'changed' => $values[$field] !== $originals[$field],
                    'error' => $this->errors[$quizid][$field] ?? null,
                    'label' => get_string($field, 'local_quizbulkedit') . ': ' . $name,
                    'colhidden' => !isset($shown[$field]),
                    'disabled' => $field === 'visible' &&
                        !has_capability('moodle/course:activityvisibility', $cm->context),
                ] + $this->control($field, $types[$field] ?? null, updater::choices($field), $values[$field]);
            }

            $rows[] = [
                'quizid' => $quizid,
                'name' => $name,
                'url' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                'editurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false),
                'section' => get_section_name($cm->get_course(), $cm->sectionnum),
                'hidden' => !$cm->visible,
                'cells' => $cells,
                'reviewopen' => $reviewchanged || $reviewerrors,
                'unusedduring' => implode(' ', $this->unused_during($quiz->preferredbehaviour)),
            ];
        }

        return [
            'actionurl' => (new moodle_url('/local/quizbulkedit/index.php'))->out(false),
            'courseid' => $this->courseid,
            'sesskey' => sesskey(),
            'fields' => $fields,
            'groups' => $groups,
            'defaultcolumns' => implode(',', updater::DEFAULTCOLUMNS),
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'colspan' => count($shown) + 1,
            'filter' => $this->filter,
            'timezone' => \core_date::get_user_timezone(),
            'allchoices' => array_map(
                fn($quizid, $label) => ['value' => $quizid, 'label' => $label],
                array_keys($copychoices),
                $copychoices
            ),
            'reviewgrid' => json_encode($this->review_grid_strings()),
        ];
    }

    /**
     * The columns to show: the user's choice, plus any with an error or unsaved change to redisplay.
     *
     * @return string[]
     */
    protected function shown_columns(): array {
        $columns = updater::columns();
        $pref = get_user_preferences('local_quizbulkedit_columns', '');
        $shown = $pref === '' ? updater::DEFAULTCOLUMNS : array_intersect($columns, explode(',', $pref));
        foreach ([$this->errors, $this->submitted] as $byquiz) {
            foreach ($byquiz as $quizid => $fields) {
                foreach (array_keys($fields) as $field) {
                    $original = $this->original[$quizid][$field] ?? null;
                    if ($byquiz === $this->submitted && $fields[$field] === $original) {
                        continue;
                    }
                    $shown[] = in_array($field, updater::REVIEWFIELDS) ? 'review' : $field;
                }
            }
        }
        // Keep display order.
        return array_values(array_intersect($columns, $shown));
    }

    /**
     * Template data for a quiz's Safe Exam Browser drop-down.
     *
     * The first option keeps the current setup; the others turn it off or copy another quiz's.
     *
     * @param int $quizid
     * @param \cm_info $cm
     * @param \stdClass $quiz with the SEB fields from seb::load()
     * @param array $sebchoices value => label: '0' for off, ways to turn it on, and every quiz using SEB
     * @param string $value current value
     * @param string $original value originally shown (the current SEB mode)
     * @return array
     */
    protected function seb_cell(
        int $quizid,
        \cm_info $cm,
        \stdClass $quiz,
        array $sebchoices,
        string $value,
        string $original
    ): array {
        // Only the ways to turn SEB on that this user may use on this quiz.
        $turnon = seb::turn_on_choices($cm->context);
        $sebchoices = array_filter(
            $sebchoices,
            fn($key) => !preg_match('/^[mt]\d+$/', $key) || isset($turnon[$key]),
            ARRAY_FILTER_USE_KEY
        );
        $choices = [$original => seb::describe($quiz)] + $sebchoices;
        unset($choices['q' . $quizid]);
        $options = [];
        foreach ($choices as $optvalue => $label) {
            $options[] = ['value' => $optvalue, 'label' => $label, 'selected' => (string) $optvalue === $value];
        }
        return [
            'isselect' => true,
            'options' => $options,
            'disabled' => $quiz->seblocked || !$quiz->sebcanedit,
            'note' => $quiz->seblocked ? get_string('seblocked', 'local_quizbulkedit') : null,
        ];
    }

    /**
     * Template data for the review options cell: hidden values, copy drop-down and Show button.
     *
     * @param int $quizid
     * @param string $name formatted quiz name
     * @param array $values field => current value string
     * @param array $originals field => original value string
     * @param array $copychoices quiz id => "Same as ..." label
     * @param array $reviewerrors review field => error message
     * @return array
     */
    protected function review_cell(
        int $quizid,
        string $name,
        array $values,
        array $originals,
        array $copychoices,
        array $reviewerrors
    ): array {
        $reviewinputs = [];
        foreach (updater::REVIEWFIELDS as $field) {
            $reviewinputs[] = ['field' => $field, 'value' => $values[$field], 'original' => $originals[$field]];
        }
        $copyoptions = [['value' => '', 'label' => get_string('reviewcopyfrom', 'local_quizbulkedit')]];
        foreach ($copychoices as $otherid => $label) {
            if ($otherid != $quizid) {
                $copyoptions[] = ['value' => $otherid, 'label' => $label];
            }
        }
        return [
            'field' => 'review',
            'isreview' => true,
            'error' => $reviewerrors ? reset($reviewerrors) : null,
            'reviewinputs' => $reviewinputs,
            'copyoptions' => $copyoptions,
            'copylabel' => get_string('reviewcopyfor', 'local_quizbulkedit', $name),
        ];
    }

    /**
     * Labels for the review option grids, which JS builds when first shown.
     *
     * @return array
     */
    protected function review_grid_strings(): array {
        $times = [];
        foreach (self::REVIEWTIMES as $time => $bit) {
            $times[] = ['key' => $time, 'bit' => $bit, 'label' => get_string('review' . $time, 'quiz')];
        }
        $items = [];
        foreach (self::REVIEWITEMS as $field => [$identifier, $component]) {
            $items[] = ['field' => $field, 'label' => get_string($identifier, $component)];
        }
        return [
            'times' => $times,
            'items' => $items,
            // Patterns: JS fills in the {...} placeholders.
            'caption' => get_string('reviewfor', 'local_quizbulkedit', '{quiz}'),
            'checkbox' => get_string(
                'reviewcheckbox',
                'local_quizbulkedit',
                (object) ['option' => '{option}', 'when' => '{when}', 'quiz' => '{quiz}']
            ),
        ];
    }

    /**
     * Review fields the question behaviour doesn't use during the attempt.
     *
     * @param string $behaviour
     * @return string[] review field names
     */
    protected function unused_during(string $behaviour): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        try {
            $unused = \question_engine::get_behaviour_unused_display_options($behaviour);
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(fn($option) => 'review' . $option, $unused);
    }

    /**
     * Template data for one input or drop-down.
     *
     * @param string $field
     * @param string|null $type input type, for free-input fields
     * @param array|null $choices value => label, for drop-downs
     * @param string $value current value
     * @return array
     */
    protected function control(string $field, ?string $type, ?array $choices, string $value): array {
        if ($choices !== null) {
            $options = [];
            foreach ($choices as $optvalue => $label) {
                $options[] = ['value' => $optvalue, 'label' => $label, 'selected' => (string) $optvalue === $value];
            }
            return ['isselect' => true, 'options' => $options];
        }
        return [
            'isselect' => false,
            'type' => $type,
            'value' => $value,
            'isnumber' => $type === 'number',
            'step' => in_array($field, [...updater::MINUTEFIELDS, 'grade', 'gradepass']) ? 'any' : '1',
        ];
    }
}
