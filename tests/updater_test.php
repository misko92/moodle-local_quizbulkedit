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

use local_quizbulkedit\local\updater;

/**
 * Tests for the updater.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizbulkedit\local\updater
 */
final class updater_test extends \advanced_testcase {
    /**
     * Course with two quizzes and an editing teacher, logged in.
     *
     * @return array [course, quiz1, quiz2]
     */
    private function setup_course(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quizgen = $gen->get_plugin_generator('mod_quiz');
        $quiz1 = $quizgen->create_instance(['course' => $course->id, 'quizpassword' => 'old1',
            'reviewcorrectness' => 0x10, 'timeopen' => 0]);
        $quiz2 = $quizgen->create_instance(['course' => $course->id, 'quizpassword' => 'old2']);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        return [$course, $quiz1, $quiz2];
    }

    /**
     * Submitted form values equal to what is shown on page load.
     *
     * @param array $quizzes
     * @return array
     */
    private function shown(array $quizzes): array {
        $shown = [];
        foreach ($quizzes as $quizid => ['quiz' => $quiz]) {
            foreach (updater::FIELDS as $field) {
                $shown[$quizid][$field] = updater::format_value($field, $quiz->$field);
            }
        }
        return $shown;
    }

    public function test_untouched_form_has_no_changes(): void {
        [$course] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $this->assertCount(2, $quizzes);
        $shown = $this->shown($quizzes);
        [$changes, $errors] = updater::collect_changes($quizzes, $shown, $shown);
        $this->assertSame([], $changes);
        $this->assertSame([], $errors);
    }

    public function test_change_passwords_only_touches_password(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['password'] = 'new1';
        $submitted[$quiz2->id]['password'] = '';

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        $this->assertSame([$quiz1->id => ['password' => 'new1'], $quiz2->id => ['password' => '']], $changes);

        $sink = $this->redirectEvents();
        $this->assertSame(2, updater::apply($course, $changes));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_updated);
        $this->assertCount(2, $events);

        $after = $DB->get_record('quiz', ['id' => $quiz1->id]);
        $this->assertSame('new1', $after->password);
        // Review options must survive (quiz_update_instance would have reset them).
        $this->assertEquals($quiz1->reviewcorrectness, $after->reviewcorrectness);
        $this->assertSame('', $DB->get_field('quiz', 'password', ['id' => $quiz2->id]));
    }

    public function test_dates_update_calendar_events(): void {
        global $DB;
        [$course, $quiz1] = $this->setup_course();
        $this->setTimezone('Australia/Perth');
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['timeopen'] = '2030-03-01T09:00';
        $submitted[$quiz1->id]['timeclose'] = '2030-03-01T10:30';
        $submitted[$quiz1->id]['timelimit'] = '45';
        $submitted[$quiz1->id]['attempts'] = '2';

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        updater::apply($course, $changes);

        $after = $DB->get_record('quiz', ['id' => $quiz1->id]);
        $tz = new \DateTimeZone('Australia/Perth');
        $this->assertEquals((new \DateTime('2030-03-01 09:00', $tz))->getTimestamp(), $after->timeopen);
        $this->assertEquals((new \DateTime('2030-03-01 10:30', $tz))->getTimestamp(), $after->timeclose);
        $this->assertEquals(45 * MINSECS, $after->timelimit);
        $this->assertEquals(2, $after->attempts);
        $this->assertEquals(2, $DB->count_records('event', ['modulename' => 'quiz', 'instance' => $quiz1->id]));
        $this->assertSame('2030-03-01T09:00', updater::format_value('timeopen', $after->timeopen));

        // Clearing the dates removes the events.
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['timeopen'] = '';
        $submitted[$quiz1->id]['timeclose'] = '';
        [$changes] = updater::collect_changes($quizzes, $submitted, $shown);
        updater::apply($course, $changes);
        $this->assertEquals(0, $DB->count_records('event', ['modulename' => 'quiz', 'instance' => $quiz1->id]));
    }

    public function test_validation_errors(): void {
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['timeopen'] = '2030-03-01T10:00';
        $submitted[$quiz1->id]['timeclose'] = '2030-03-01T09:00';
        $submitted[$quiz1->id]['attempts'] = '-1';
        $submitted[$quiz2->id]['timelimit'] = 'abc';
        $submitted[$quiz2->id]['timeopen'] = '2030-02-31T10:00';

        [, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertEqualsCanonicalizing(['timeclose', 'attempts'], array_keys($errors[$quiz1->id]));
        $this->assertEqualsCanonicalizing(['timelimit', 'timeopen'], array_keys($errors[$quiz2->id]));
    }

    public function test_stale_page_does_not_clobber_other_edits(): void {
        global $DB;
        [$course, $quiz1] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        // Someone changes the password in the normal settings form after the page loaded.
        $DB->set_field('quiz', 'password', 'elsewhere', ['id' => $quiz1->id]);
        $quizzes = updater::get_quizzes($course);
        // The bulk page is submitted without touching that quiz's password.
        [$changes] = updater::collect_changes($quizzes, $shown, $shown);
        $this->assertSame([], $changes);
    }

    public function test_quizzes_without_capability_are_ignored(): void {
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $cm = get_coursemodule_from_instance('quiz', $quiz2->id);
        $role = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $role, \context_module::instance($cm->id));

        $quizzes = updater::get_quizzes($course);
        $this->assertEquals([$quiz1->id], array_keys($quizzes));
        [$changes] = updater::collect_changes($quizzes, [$quiz2->id => ['password' => 'x']], []);
        $this->assertSame([], $changes);

        $this->expectException(\required_capability_exception::class);
        updater::apply($course, [$quiz2->id => ['password' => 'x']]);
    }

    public function test_visibility_grademethod_and_review_copy(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $DB->set_field('quiz', 'reviewrightanswer', 0x1111, ['id' => $quiz1->id]);
        $DB->set_field('quiz', 'reviewrightanswer', 0, ['id' => $quiz2->id]);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz2->id]['visible'] = '0';
        $submitted[$quiz2->id]['grademethod'] = (string) QUIZ_ATTEMPTLAST;
        $submitted[$quiz2->id]['reviewfrom'] = (string) $quiz1->id;
        // Copying from itself is a no-op.
        $submitted[$quiz1->id]['reviewfrom'] = (string) $quiz1->id;

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        $this->assertEquals([$quiz2->id], array_keys($changes));
        $this->assertSame(1, updater::apply($course, $changes));

        $after = $DB->get_record('quiz', ['id' => $quiz2->id]);
        $this->assertEquals(QUIZ_ATTEMPTLAST, $after->grademethod);
        $this->assertEquals(0x1111, $after->reviewrightanswer);
        $this->assertEquals(0, $DB->get_field(
            'course_modules',
            'visible',
            ['id' => get_coursemodule_from_instance('quiz', $quiz2->id)->id]
        ));
        $this->assertEquals(0, updater::get_quizzes($course)[$quiz2->id]['cm']->visible);
    }

    public function test_invalid_choices(): void {
        [$course, $quiz1] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['grademethod'] = '99';
        $submitted[$quiz1->id]['browsersecurity'] = 'nonsense';
        $submitted[$quiz1->id]['reviewfrom'] = '123456789';
        $submitted[$quiz1->id]['visible'] = '2';
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $changes);
        $this->assertEqualsCanonicalizing(
            ['grademethod', 'browsersecurity', 'reviewfrom', 'visible'],
            array_keys($errors[$quiz1->id])
        );
    }

    public function test_visibility_needs_capability(): void {
        [$course, $quiz1] = $this->setup_course();
        $role = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $role, \context_course::instance($course->id));
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['visible'] = '0';
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $changes);
        $this->assertArrayHasKey('visible', $errors[$quiz1->id]);
    }
}
