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
        $hints = ['subnet', 'timelimit', 'attempts', 'reviewfrom'];
        $fieldnames = updater::FIELDS;

        $fields = [];
        $choices = [];
        foreach ($fieldnames as $field) {
            $choices[$field] = updater::choices($field, $this->quizzes);
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_quizbulkedit'),
                'hint' => in_array($field, $hints) ? get_string($field . '_hint', 'local_quizbulkedit') : null,
                'isreview' => $field === 'reviewfrom',
            ] + $this->control($field, $types[$field] ?? null, $choices[$field], '');
        }

        $rows = [];
        foreach ($this->quizzes as $quizid => ['cm' => $cm, 'quiz' => $quiz]) {
            $cells = [];
            foreach ($fieldnames as $field) {
                $original = $this->original[$quizid][$field] ?? updater::format_value($field, $quiz->$field);
                $value = $this->submitted[$quizid][$field] ?? $original;
                $fieldchoices = $choices[$field];
                if ($field === 'reviewfrom') {
                    // A quiz can't copy review options from itself.
                    unset($fieldchoices[$quizid]);
                }
                $cells[] = [
                    'field' => $field,
                    'original' => $original,
                    'changed' => $value !== $original,
                    'error' => $this->errors[$quizid][$field] ?? null,
                    'label' => get_string($field, 'local_quizbulkedit') . ': ' . $cm->get_formatted_name(),
                    'isreview' => $field === 'reviewfrom',
                    'disabled' => $field === 'visible' &&
                        !has_capability('moodle/course:activityvisibility', $cm->context),
                ] + $this->control($field, $types[$field] ?? null, $fieldchoices, $value);
            }
            $rows[] = [
                'quizid' => $quizid,
                'name' => $cm->get_formatted_name(),
                'url' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                'editurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false),
                'section' => get_section_name($cm->get_course(), $cm->sectionnum),
                'hidden' => !$cm->visible,
                'cells' => $cells,
                'review' => $this->review_grid($quiz),
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
     * Template data for the read-only grid of a quiz's review options.
     *
     * @param \stdClass $quiz
     * @return array rows of [label, cells => [on]]
     */
    protected function review_grid(\stdClass $quiz): array {
        $items = [];
        foreach (self::REVIEWITEMS as $field => [$identifier, $component]) {
            $cells = [];
            foreach (self::REVIEWTIMES as $when) {
                $cells[] = ['on' => (bool) ($quiz->$field & $when)];
            }
            $items[] = ['label' => get_string($identifier, $component), 'cells' => $cells];
        }
        return $items;
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
