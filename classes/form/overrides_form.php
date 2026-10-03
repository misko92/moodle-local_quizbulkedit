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

namespace local_quizbulkedit\form;

use moodleform;

/**
 * Form for adding, updating or removing overrides on many quizzes at once.
 *
 * Custom data: users (id => name), groups (id => name), quizzes (id => name), canapply (bool).
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overrides_form extends moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $users = $this->_customdata['users'];
        $groups = $this->_customdata['groups'];
        $quizzes = $this->_customdata['quizzes'];

        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'action', get_string('ovaction', 'local_quizbulkedit'), [
            'save' => get_string('ovactionsave', 'local_quizbulkedit'),
            'delete' => get_string('ovactiondelete', 'local_quizbulkedit'),
        ]);

        // Who.
        $mform->addElement('header', 'whohdr', get_string('ovwho', 'local_quizbulkedit'));
        $mform->setExpanded('whohdr');
        $mform->addElement(
            'autocomplete',
            'users',
            get_string('ovstudents', 'local_quizbulkedit'),
            $users,
            ['multiple' => true, 'noselectionstring' => get_string('ovnoneselected', 'local_quizbulkedit')]
        );
        if ($groups) {
            $mform->addElement(
                'autocomplete',
                'groups',
                get_string('ovgroups', 'local_quizbulkedit'),
                $groups,
                ['multiple' => true, 'noselectionstring' => get_string('ovnoneselected', 'local_quizbulkedit')]
            );
        }

        // Which quizzes.
        $mform->addElement('header', 'quizhdr', get_string('ovquizzes', 'local_quizbulkedit'));
        $mform->setExpanded('quizhdr');
        $mform->addElement('advcheckbox', 'allquizzes', '', get_string('ovallquizzes', 'local_quizbulkedit', count($quizzes)));
        $mform->addElement(
            'autocomplete',
            'quizzes',
            get_string('ovchoosequizzes', 'local_quizbulkedit'),
            $quizzes,
            ['multiple' => true, 'noselectionstring' => get_string('ovnoneselected', 'local_quizbulkedit')]
        );
        $mform->hideIf('quizzes', 'allquizzes', 'checked');

        // What.
        $mform->addElement('header', 'whathdr', get_string('ovwhat', 'local_quizbulkedit'));
        $mform->setExpanded('whathdr');
        $mform->hideIf('whathdr', 'action', 'eq', 'delete');
        $keep = get_string('ovkeep', 'local_quizbulkedit');

        $mform->addElement('select', 'timelimitmode', get_string('ovtimelimit', 'local_quizbulkedit'), [
            'none' => $keep,
            'multiply' => get_string('ovmultiply', 'local_quizbulkedit'),
            'add' => get_string('ovaddminutes', 'local_quizbulkedit'),
            'set' => get_string('ovsetminutes', 'local_quizbulkedit'),
        ]);
        $mform->addElement('float', 'timelimitfactor', get_string('ovfactor', 'local_quizbulkedit'));
        $mform->setDefault('timelimitfactor', 1.5);
        $mform->hideIf('timelimitfactor', 'timelimitmode', 'neq', 'multiply');
        $mform->addElement('float', 'timelimitminutes', get_string('ovminutes', 'local_quizbulkedit'));
        $mform->setDefault('timelimitminutes', 0);
        $mform->hideIf('timelimitminutes', 'timelimitmode', 'in', ['none', 'multiply']);

        $mform->addElement(
            'select',
            'attemptsmode',
            get_string('ovattempts', 'local_quizbulkedit'),
            ['none' => $keep, 'set' => get_string('ovset', 'local_quizbulkedit')]
        );
        $mform->addElement('text', 'attempts', get_string('ovattemptsvalue', 'local_quizbulkedit'), ['size' => 4]);
        $mform->setType('attempts', PARAM_INT);
        $mform->setDefault('attempts', 0);
        $mform->hideIf('attempts', 'attemptsmode', 'eq', 'none');

        $mform->addElement(
            'select',
            'timeopenmode',
            get_string('ovtimeopen', 'local_quizbulkedit'),
            ['none' => $keep, 'set' => get_string('ovset', 'local_quizbulkedit')]
        );
        $mform->addElement('date_time_selector', 'timeopen', get_string('ovtimeopenvalue', 'local_quizbulkedit'));
        $mform->hideIf('timeopen', 'timeopenmode', 'eq', 'none');

        if (\local_quizbulkedit\local\updater::has_duedate()) {
            $mform->addElement('select', 'duedatemode', get_string('ovduedate', 'local_quizbulkedit'), [
                'none' => $keep,
                'set' => get_string('ovset', 'local_quizbulkedit'),
                'add' => get_string('ovaddtodue', 'local_quizbulkedit'),
            ]);
            $mform->addElement('date_time_selector', 'duedate', get_string('ovduedatevalue', 'local_quizbulkedit'));
            $mform->hideIf('duedate', 'duedatemode', 'neq', 'set');
            $mform->addElement('float', 'dueminutes', get_string('ovminutes', 'local_quizbulkedit'));
            $mform->setDefault('dueminutes', 0);
            $mform->hideIf('dueminutes', 'duedatemode', 'neq', 'add');
        }

        $mform->addElement('select', 'timeclosemode', get_string('ovtimeclose', 'local_quizbulkedit'), [
            'none' => $keep,
            'set' => get_string('ovset', 'local_quizbulkedit'),
            'add' => get_string('ovaddtoclose', 'local_quizbulkedit'),
        ]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('ovtimeclosevalue', 'local_quizbulkedit'));
        $mform->hideIf('timeclose', 'timeclosemode', 'neq', 'set');
        $mform->addElement('float', 'timecloseminutes', get_string('ovminutes', 'local_quizbulkedit'));
        $mform->setDefault('timecloseminutes', 0);
        $mform->hideIf('timecloseminutes', 'timeclosemode', 'neq', 'add');

        $mform->addElement(
            'select',
            'passwordmode',
            get_string('ovpassword', 'local_quizbulkedit'),
            ['none' => $keep, 'set' => get_string('ovset', 'local_quizbulkedit')]
        );
        $mform->addElement('text', 'password', get_string('ovpasswordvalue', 'local_quizbulkedit'));
        $mform->setType('password', PARAM_TEXT);
        $mform->hideIf('password', 'passwordmode', 'eq', 'none');

        $mform->addElement('text', 'reason', get_string('ovreason', 'local_quizbulkedit'), ['size' => 50]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addHelpButton('reason', 'ovreason', 'local_quizbulkedit');

        $buttons = [$mform->createElement('submit', 'previewbutton', get_string('ovpreview', 'local_quizbulkedit'))];
        if ($this->_customdata['canapply']) {
            $buttons[] = $mform->createElement('submit', 'applybutton', get_string('ovapply', 'local_quizbulkedit'));
        }
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        // Keep the buttons out of the Settings section, which is hidden when removing overrides.
        $mform->closeHeaderBefore('buttonar');
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['users']) && empty($data['groups'])) {
            $errors['users'] = get_string('ovneedwho', 'local_quizbulkedit');
        }
        if (empty($data['allquizzes']) && empty($data['quizzes'])) {
            $errors['quizzes'] = get_string('ovneedquiz', 'local_quizbulkedit');
        }
        if ($data['action'] === 'save') {
            $modes = ['timelimitmode', 'attemptsmode', 'timeopenmode', 'timeclosemode', 'passwordmode', 'duedatemode'];
            if (!array_filter($modes, fn($mode) => ($data[$mode] ?? 'none') !== 'none') && trim($data['reason'] ?? '') === '') {
                $errors['timelimitmode'] = get_string('ovneedsetting', 'local_quizbulkedit');
            }
            if ($data['timelimitmode'] === 'multiply' && $data['timelimitfactor'] <= 0) {
                $errors['timelimitfactor'] = get_string('ovpositive', 'local_quizbulkedit');
            }
            if ($data['timelimitmode'] === 'set' && $data['timelimitminutes'] <= 0) {
                $errors['timelimitminutes'] = get_string('ovpositive', 'local_quizbulkedit');
            }
            if ($data['timelimitmode'] === 'add' && $data['timelimitminutes'] < 0) {
                $errors['timelimitminutes'] = get_string('errorminutes', 'local_quizbulkedit');
            }
            if (($data['duedatemode'] ?? 'none') === 'add' && $data['dueminutes'] < 0) {
                $errors['dueminutes'] = get_string('errorminutes', 'local_quizbulkedit');
            }
            if ($data['timeclosemode'] === 'add' && $data['timecloseminutes'] < 0) {
                $errors['timecloseminutes'] = get_string('errorminutes', 'local_quizbulkedit');
            }
            if ($data['attemptsmode'] === 'set' && $data['attempts'] < 0) {
                $errors['attempts'] = get_string('errorattempts', 'local_quizbulkedit');
            }
            if (
                $data['timeopenmode'] === 'set' && $data['timeclosemode'] === 'set' &&
                $data['timeclose'] <= $data['timeopen']
            ) {
                $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
            }
        }
        return $errors;
    }
}
