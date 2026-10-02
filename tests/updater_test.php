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

    public function test_visibility_grademethod_and_review_options(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $DB->set_field('quiz', 'reviewrightanswer', 0, ['id' => $quiz2->id]);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz2->id]['visible'] = '0';
        $submitted[$quiz2->id]['grademethod'] = (string) QUIZ_ATTEMPTLAST;
        $submitted[$quiz2->id]['reviewattempt'] = (string) 0x11110;
        $submitted[$quiz2->id]['reviewrightanswer'] = (string) 0x01010;

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        $this->assertEquals([$quiz2->id], array_keys($changes));
        $this->assertSame(1, updater::apply($course, $changes));

        $after = $DB->get_record('quiz', ['id' => $quiz2->id]);
        $this->assertEquals(QUIZ_ATTEMPTLAST, $after->grademethod);
        $this->assertEquals(0x01010, $after->reviewrightanswer);
        $this->assertEquals(0x11110, $after->reviewattempt);
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
        $submitted[$quiz1->id]['reviewmarks'] = '123456789';
        $submitted[$quiz1->id]['visible'] = '2';
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $changes);
        $this->assertEqualsCanonicalizing(
            ['grademethod', 'reviewmarks', 'visible'],
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

    public function test_subnet(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['subnet'] = '192.168.10.0/24,10.1. , 172.16.0.1-50, 2001:db8::/32';
        $submitted[$quiz2->id]['subnet'] = 'school wifi';

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame(['subnet'], array_keys($errors[$quiz2->id]));
        $this->assertArrayNotHasKey($quiz1->id, $errors);

        unset($changes[$quiz2->id]);
        updater::apply($course, $changes);
        $subnet = $DB->get_field('quiz', 'subnet', ['id' => $quiz1->id]);
        $this->assertSame('192.168.10.0/24, 10.1., 172.16.0.1-50, 2001:db8::/32', $subnet);
        $this->assertTrue(address_in_subnet('10.1.2.3', $subnet));
        $this->assertTrue(address_in_subnet('172.16.0.42', $subnet));
        $this->assertFalse(address_in_subnet('8.8.8.8', $subnet));

        // Clearing it removes the restriction.
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['subnet'] = '';
        [$changes] = updater::collect_changes($quizzes, $submitted, $shown);
        updater::apply($course, $changes);
        $this->assertSame('', $DB->get_field('quiz', 'subnet', ['id' => $quiz1->id]));
    }

    public function test_review_options_follow_quiz_form_rules(): void {
        global $DB;
        [$course, $quiz1] = $this->setup_course();
        $DB->set_field('quiz', 'preferredbehaviour', 'deferredfeedback', ['id' => $quiz1->id]);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        // The attempt only during: the details after the attempt can't be shown.
        $submitted[$quiz1->id]['reviewattempt'] = '0';
        // During is unused by deferred feedback; immediately after needs the attempt.
        $submitted[$quiz1->id]['reviewrightanswer'] = (string) 0x11000;
        // Marks need max marks at the same time.
        $submitted[$quiz1->id]['reviewmaxmarks'] = (string) 0x00100;
        $submitted[$quiz1->id]['reviewmarks'] = (string) 0x00110;
        // Overall feedback can never be shown during the attempt.
        $submitted[$quiz1->id]['reviewoverallfeedback'] = (string) 0x10010;

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        updater::apply($course, $changes);

        $after = $DB->get_record('quiz', ['id' => $quiz1->id]);
        $this->assertEquals(0x10000, $after->reviewattempt);
        $this->assertEquals(0, $after->reviewrightanswer);
        $this->assertEquals(0x00100, $after->reviewmarks);
        $this->assertEquals(0x00010, $after->reviewoverallfeedback);

        $this->assertSame(
            (string) $after->reviewcorrectness,
            updater::format_value('reviewcorrectness', $after->reviewcorrectness)
        );
    }

    public function test_untouched_nonconforming_review_options_are_left_alone(): void {
        global $DB;
        [$course, $quiz1] = $this->setup_course();
        // E.g. set by an older Moodle or a restore: marks without max marks.
        $DB->set_field('quiz', 'reviewmaxmarks', 0, ['id' => $quiz1->id]);
        $DB->set_field('quiz', 'reviewmarks', 0x00010, ['id' => $quiz1->id]);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['password'] = 'changed';
        [$changes] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([$quiz1->id => ['password' => 'changed']], $changes);
    }

    public function test_easy_settings(): void {
        global $DB;
        [$course, $quiz1] = $this->setup_course();
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $new = [
            'overduehandling' => 'autosubmit',
            'navmethod' => 'sequential',
            'delay1' => '30',
            'delay2' => '1.5',
            'shuffleanswers' => '0',
            'attemptonlast' => '1',
            'canredoquestions' => '1',
            'showuserpicture' => (string) QUIZ_SHOWIMAGE_LARGE,
            'decimalpoints' => '0',
            'questiondecimalpoints' => '-1',
            'showblocks' => '1',
        ];
        $submitted[$quiz1->id] = $new + $submitted[$quiz1->id];
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        updater::apply($course, $changes);

        $after = $DB->get_record('quiz', ['id' => $quiz1->id]);
        $this->assertSame('autosubmit', $after->overduehandling);
        $this->assertSame('sequential', $after->navmethod);
        $this->assertEquals(30 * MINSECS, $after->delay1);
        $this->assertEquals(90, $after->delay2);
        $this->assertEquals(0, $after->shuffleanswers);
        $this->assertEquals(1, $after->attemptonlast);
        $this->assertEquals(1, $after->canredoquestions);
        $this->assertEquals(QUIZ_SHOWIMAGE_LARGE, $after->showuserpicture);
        $this->assertEquals(0, $after->decimalpoints);
        $this->assertEquals(-1, $after->questiondecimalpoints);
        $this->assertEquals(1, $after->showblocks);
        foreach ($new as $field => $value) {
            $this->assertSame($value, updater::format_value($field, $after->$field), $field);
        }
    }

    public function test_easy_settings_validation(): void {
        [$course, $quiz1, $quiz2] = $this->setup_course();
        set_config('graceperiodmin', 60, 'quiz');
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['overduehandling'] = 'graceperiod';
        $submitted[$quiz1->id]['graceperiod'] = '1';
        $submitted[$quiz1->id]['navmethod'] = 'sideways';
        $submitted[$quiz1->id]['decimalpoints'] = '9';
        $submitted[$quiz1->id]['delay1'] = '-5';
        // Long enough: no error.
        $submitted[$quiz2->id]['overduehandling'] = 'graceperiod';
        $submitted[$quiz2->id]['graceperiod'] = '10';

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertEqualsCanonicalizing(
            ['graceperiod', 'navmethod', 'decimalpoints', 'delay1'],
            array_keys($errors[$quiz1->id])
        );
        $this->assertArrayNotHasKey($quiz2->id, $errors);
        $this->assertSame(['overduehandling' => 'graceperiod', 'graceperiod' => 600], $changes[$quiz2->id]);
    }
}
