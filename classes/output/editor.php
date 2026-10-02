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
            'timelimit' => 'number',
            'attempts' => 'number',
        ];
        $hints = ['subnet', 'timelimit', 'attempts', 'review'];
        // One column per field, except the review options, which share one column.
        $columns = array_diff(updater::FIELDS, updater::REVIEWFIELDS);

        $copychoices = [];
        foreach ($this->quizzes as $quizid => ['cm' => $cm]) {
            $copychoices[$quizid] = get_string('reviewcopy', 'local_quizbulkedit', $cm->get_formatted_name());
        }

        $fields = [];
        foreach ([...$columns, 'review'] as $field) {
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_quizbulkedit'),
                'hint' => in_array($field, $hints) ? get_string($field . '_hint', 'local_quizbulkedit') : null,
                'isreview' => $field === 'review',
            ] + $this->control(
                $field,
                $types[$field] ?? null,
                $field === 'review' ? $copychoices : updater::choices($field),
                ''
            );
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

            $cells = [];
            foreach ($columns as $field) {
                $cells[] = [
                    'field' => $field,
                    'original' => $originals[$field],
                    'changed' => $values[$field] !== $originals[$field],
                    'error' => $this->errors[$quizid][$field] ?? null,
                    'label' => get_string($field, 'local_quizbulkedit') . ': ' . $name,
                    'disabled' => $field === 'visible' &&
                        !has_capability('moodle/course:activityvisibility', $cm->context),
                ] + $this->control($field, $types[$field] ?? null, updater::choices($field), $values[$field]);
            }

            $reviewinputs = [];
            $reviewchanged = false;
            foreach (updater::REVIEWFIELDS as $field) {
                $reviewinputs[] = ['field' => $field, 'value' => $values[$field], 'original' => $originals[$field]];
                $reviewchanged = $reviewchanged || $values[$field] !== $originals[$field];
            }
            $reviewerrors = array_intersect_key($this->errors[$quizid] ?? [], array_flip(updater::REVIEWFIELDS));
            $copyoptions = [['value' => '', 'label' => get_string('reviewcopyfrom', 'local_quizbulkedit')]];
            foreach ($copychoices as $otherid => $label) {
                if ($otherid != $quizid) {
                    $copyoptions[] = ['value' => $otherid, 'label' => $label];
                }
            }
            $cells[] = [
                'field' => 'review',
                'isreview' => true,
                'changed' => $reviewchanged,
                'error' => $reviewerrors ? reset($reviewerrors) : null,
                'reviewinputs' => $reviewinputs,
                'copyoptions' => $copyoptions,
                'copylabel' => get_string('reviewcopyfor', 'local_quizbulkedit', $name),
            ];

            $rows[] = [
                'quizid' => $quizid,
                'name' => $name,
                'url' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                'editurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false),
                'section' => get_section_name($cm->get_course(), $cm->sectionnum),
                'hidden' => !$cm->visible,
                'cells' => $cells,
                'review' => $this->review_grid($quizid, $name, $values, $originals),
                'reviewopen' => $reviewchanged || $reviewerrors,
                'unusedduring' => implode(' ', $this->unused_during($quiz->preferredbehaviour)),
            ];
        }

        return [
            'actionurl' => (new moodle_url('/local/quizbulkedit/index.php'))->out(false),
            'courseid' => $this->courseid,
            'sesskey' => sesskey(),
            'fields' => $fields,
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'colspan' => count($fields) + 1,
            'filter' => $this->filter,
            'reviewtimes' => array_map(
                fn($time) => ['label' => get_string('review' . $time, 'quiz')],
                array_keys(self::REVIEWTIMES)
            ),
        ];
    }

    /**
     * Template data for the grid of a quiz's review option checkboxes.
     *
     * @param int $quizid
     * @param string $name formatted quiz name
     * @param array $values field => current value string
     * @param array $originals field => original value string
     * @return array rows of [label, cells => [field, bit, on, changed, id, label]]
     */
    protected function review_grid(int $quizid, string $name, array $values, array $originals): array {
        $items = [];
        foreach (self::REVIEWITEMS as $field => [$identifier, $component]) {
            $option = get_string($identifier, $component);
            $cells = [];
            foreach (self::REVIEWTIMES as $time => $bit) {
                $on = (bool) ((int) $values[$field] & $bit);
                $cells[] = [
                    'field' => $field,
                    'bit' => $bit,
                    'on' => $on,
                    'changed' => $on !== (bool) ((int) $originals[$field] & $bit),
                    'id' => "local_quizbulkedit_{$quizid}_{$field}_{$time}",
                    'label' => get_string('reviewcheckbox', 'local_quizbulkedit', (object) [
                        'option' => $option,
                        'when' => get_string('review' . $time, 'quiz'),
                        'quiz' => $name,
                    ]),
                ];
            }
            $items[] = ['label' => $option, 'cells' => $cells];
        }
        return $items;
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
            'step' => $field === 'timelimit' ? 'any' : '1',
        ];
    }
}
