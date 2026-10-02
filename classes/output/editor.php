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
    /**
     * Constructor.
     *
     * @param int $courseid
     * @param array $quizzes from updater::get_quizzes()
     * @param array $submitted quiz id => field => string, to redisplay after a failed save
     * @param array $original quiz id => field => string originally shown, kept across a failed save
     * @param array $errors quiz id => field => message
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
        protected array $errors = []
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $types = [
            'password' => 'text',
            'timeopen' => 'datetime-local',
            'timeclose' => 'datetime-local',
            'timelimit' => 'number',
            'attempts' => 'number',
        ];
        $hints = ['timelimit', 'attempts', 'reviewfrom'];
        $fieldnames = updater::get_fields();

        $fields = [];
        $choices = [];
        foreach ($fieldnames as $field) {
            $choices[$field] = updater::choices($field, $this->quizzes);
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_quizbulkedit'),
                'hint' => in_array($field, $hints) ? get_string($field . '_hint', 'local_quizbulkedit') : null,
            ] + $this->control($field, $types[$field] ?? null, $choices[$field], '');
        }

        $rows = [];
        foreach ($this->quizzes as $quizid => ['cm' => $cm, 'quiz' => $quiz]) {
            $cells = [];
            foreach ($fieldnames as $field) {
                $original = $this->original[$quizid][$field] ?? updater::format_value($field, $quiz->$field);
                $value = $this->submitted[$quizid][$field] ?? $original;
                $fieldchoices = $choices[$field];
                if ($fieldchoices !== null) {
                    if ($field === 'reviewfrom') {
                        unset($fieldchoices[$quizid]);
                    } else if (!array_key_exists($original, $fieldchoices)) {
                        // E.g. a browser security rule that has since been disabled: keep it selectable.
                        $fieldchoices[$original] = $original;
                    }
                }
                $cells[] = [
                    'field' => $field,
                    'original' => $original,
                    'changed' => $value !== $original,
                    'error' => $this->errors[$quizid][$field] ?? null,
                    'label' => get_string($field, 'local_quizbulkedit') . ': ' . $cm->get_formatted_name(),
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
            ];
        }

        return [
            'actionurl' => (new moodle_url('/local/quizbulkedit/index.php'))->out(false),
            'courseid' => $this->courseid,
            'sesskey' => sesskey(),
            'fields' => $fields,
            'rows' => $rows,
            'hasrows' => !empty($rows),
        ];
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
