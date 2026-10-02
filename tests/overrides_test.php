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

namespace local_quizbulkedit;

use local_quizbulkedit\local\overrides;

/**
 * Tests for bulk overrides.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizbulkedit\local\overrides
 */
final class overrides_test extends \advanced_testcase {
    /**
     * Form data with nothing changed.
     *
     * @param array $changes
     * @return \stdClass
     */
    private function data(array $changes): \stdClass {
        return (object) ($changes + [
            'action' => 'save', 'timelimitmode' => 'none', 'timelimitfactor' => 1.5, 'timelimitminutes' => 0,
            'attemptsmode' => 'none', 'attempts' => 0, 'timeopenmode' => 'none', 'timeopen' => 0,
            'timeclosemode' => 'none', 'timeclose' => 0, 'timecloseminutes' => 0,
            'passwordmode' => 'none', 'password' => '', 'reason' => '',
        ]);
    }

    public function test_extra_time_merges_into_existing_overrides(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quizgen = $gen->get_plugin_generator('mod_quiz');
        $timed = $quizgen->create_instance(['course' => $course->id, 'timelimit' => HOURSECS, 'timeclose' => 2000000000]);
        $untimed = $quizgen->create_instance(['course' => $course->id, 'timelimit' => 0]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        // The student already has an extra attempt on the timed quiz.
        $DB->insert_record('quiz_overrides', ['quiz' => $timed->id, 'userid' => $student->id, 'attempts' => 3]);

        $quizzes = overrides::get_quizzes($course);
        $targets = [['userid' => $student->id, 'name' => 'Student']];
        $plan = overrides::plan($quizzes, $targets, $this->data([
            'timelimitmode' => 'multiply', 'timelimitfactor' => 1.5,
            'timeclosemode' => 'add', 'timecloseminutes' => 30,
            'reason' => 'IEP: time and a half',
        ]));
        $byquiz = array_column($plan, null, 'quizid');
        $this->assertSame('update', $byquiz[$timed->id]['action']);
        // No time limit or close date to extend, only the reason: nothing to override.
        $this->assertSame('skip', $byquiz[$untimed->id]['action']);

        $this->assertSame([1, 0], overrides::apply($quizzes, $plan));
        $override = $DB->get_record('quiz_overrides', ['quiz' => $timed->id, 'userid' => $student->id]);
        $this->assertEquals(90 * MINSECS, $override->timelimit);
        $this->assertEquals(2000000000 + 30 * MINSECS, $override->timeclose);
        // Kept, not wiped.
        $this->assertEquals(3, $override->attempts);
        $this->assertSame('IEP: time and a half', $override->reason);
        $this->assertFalse($DB->record_exists('quiz_overrides', ['quiz' => $untimed->id]));

        // Running it again changes nothing.
        $plan = overrides::plan(overrides::get_quizzes($course), $targets, $this->data([
            'timelimitmode' => 'multiply', 'timelimitfactor' => 1.5,
        ]));
        $this->assertSame(['skip', 'skip'], array_column($plan, 'action'));
    }

    public function test_groups_and_removal(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'attempts' => 1]);
        $group = $gen->create_group(['courseid' => $course->id]);
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));

        $quizzes = overrides::get_quizzes($course);
        $targets = [['groupid' => $group->id, 'name' => 'Group']];
        $plan = overrides::plan($quizzes, $targets, $this->data([
            'attemptsmode' => 'set', 'attempts' => 2, 'passwordmode' => 'set', 'password' => 'groupB',
        ]));
        $this->assertSame([1, 0], overrides::apply($quizzes, $plan));
        $override = $DB->get_record('quiz_overrides', ['quiz' => $quiz->id, 'groupid' => $group->id]);
        $this->assertEquals(2, $override->attempts);
        $this->assertSame('groupB', $override->password);

        $plan = overrides::plan($quizzes, $targets, $this->data(['action' => 'delete']));
        $this->assertSame('delete', $plan[0]['action']);
        $this->assertSame([0, 1], overrides::apply($quizzes, $plan));
        $this->assertFalse($DB->record_exists('quiz_overrides', ['quiz' => $quiz->id]));
    }

    public function test_close_before_open_is_skipped(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'timeopen' => 2000000000]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));

        $plan = overrides::plan(
            overrides::get_quizzes($course),
            [['userid' => $student->id, 'name' => 'S']],
            $this->data(['timeclosemode' => 'set', 'timeclose' => 1900000000])
        );
        $this->assertSame('skip', $plan[0]['action']);
        $this->assertSame(get_string('closebeforeopen', 'quiz'), $plan[0]['note']);
    }

    public function test_needs_manage_overrides_capability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $this->setUser($gen->create_and_enrol($course, 'student'));
        $this->assertSame([], overrides::get_quizzes($course));
    }
}
