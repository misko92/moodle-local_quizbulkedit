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

    public function test_name_grade_and_grade_to_pass(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $DB->set_field('quiz', 'grade', 10, ['id' => $quiz1->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record('quiz_grades', ['quiz' => $quiz1->id, 'userid' => $student->id, 'grade' => 8, 'timemodified' => time()]);

        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $this->assertSame('10', $shown[$quiz1->id]['grade']);
        $submitted = $shown;
        $submitted[$quiz1->id]['name'] = 'Renamed quiz';
        $submitted[$quiz1->id]['grade'] = '5';
        $submitted[$quiz1->id]['gradepass'] = '2.5';
        // Same number written differently: not a change.
        $submitted[$quiz2->id]['grade'] = $shown[$quiz2->id]['grade'] . '.000';

        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        $this->assertEquals([$quiz1->id], array_keys($changes));
        updater::apply($course, $changes);

        $this->assertSame('Renamed quiz', $DB->get_field('quiz', 'name', ['id' => $quiz1->id]));
        $this->assertEquals(5, $DB->get_field('quiz', 'grade', ['id' => $quiz1->id]));
        // The student's 8/10 is rescaled to 4/5.
        $this->assertEquals(4, $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz1->id, 'userid' => $student->id]));
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz1->id, 'itemnumber' => 0]);
        $this->assertSame('Renamed quiz', $item->itemname);
        $this->assertEquals(5, $item->grademax);
        $this->assertEquals(2.5, $item->gradepass);
        $this->assertSame('2.5', updater::get_quizzes($course)[$quiz1->id]['quiz']->gradepass . '');
    }

    public function test_grade_to_pass_validation(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $DB->set_field('quiz', 'grade', 10, ['id' => $quiz1->id]);
        $DB->set_field('quiz', 'grade', 10, ['id' => $quiz2->id]);
        $DB->set_field('quiz', 'preferredbehaviour', 'deferredcbm', ['id' => $quiz2->id]);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['gradepass'] = '11';
        $submitted[$quiz1->id]['name'] = '   ';
        // CBM quizzes may have a grade to pass above the maximum grade, as in the settings form.
        $submitted[$quiz2->id]['gradepass'] = '11';
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertEqualsCanonicalizing(['gradepass', 'name'], array_keys($errors[$quiz1->id]));
        $this->assertArrayNotHasKey($quiz2->id, $errors);

        // Lowering the maximum grade below the grade to pass is caught too.
        $submitted = $shown;
        $submitted[$quiz1->id]['gradepass'] = '6';
        [$changes] = updater::collect_changes($quizzes, $submitted, $shown);
        updater::apply($course, $changes);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['grade'] = '5';
        [, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertArrayHasKey('grade', $errors[$quiz1->id]);

        // Completion needing a passing grade means it can't be 0.
        $cm = get_coursemodule_from_instance('quiz', $quiz1->id);
        $DB->set_field('course_modules', 'completionpassgrade', 1, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);
        $quizzes = updater::get_quizzes($course);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['gradepass'] = '0';
        [, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertArrayHasKey('gradepass', $errors[$quiz1->id]);
    }

    /**
     * Turn on SEB for a quiz.
     *
     * @param \stdClass $quiz
     * @param int $mode settings_provider::USE_SEB_ constant
     */
    private function enable_seb(\stdClass $quiz, int $mode): void {
        global $CFG;
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        if ($mode === \quizaccess_seb\settings_provider::USE_SEB_UPLOAD_CONFIG) {
            get_file_storage()->create_file_from_string([
                'contextid' => \context_module::instance($cm->id)->id, 'component' => 'quizaccess_seb',
                'filearea' => 'filemanager_sebconfigfile', 'itemid' => 0, 'filepath' => '/', 'filename' => 'exam.seb',
            ], file_get_contents($CFG->dirroot . '/mod/quiz/accessrule/seb/tests/fixtures/unencrypted.seb'));
        }
        (new \quizaccess_seb\seb_quiz_settings(0, (object) [
            'quizid' => $quiz->id, 'cmid' => $cm->id, 'requiresafeexambrowser' => $mode,
        ]))->save();
    }

    public function test_seb_copy_and_turn_off(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $quiz3 = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $this->setAdminUser();
        $this->enable_seb($quiz1, \quizaccess_seb\settings_provider::USE_SEB_UPLOAD_CONFIG);
        $this->enable_seb($quiz3, \quizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG);

        $quizzes = updater::get_quizzes($course);
        $this->assertEquals(\quizaccess_seb\settings_provider::USE_SEB_UPLOAD_CONFIG, $quizzes[$quiz1->id]['quiz']->seb);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz2->id]['seb'] = 'q' . $quiz1->id;
        $submitted[$quiz3->id]['seb'] = '0';
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $errors);
        updater::apply($course, $changes);

        // Quiz 2 now has quiz 1's uploaded config, with its own copy of the file.
        $copy = \quizaccess_seb\seb_quiz_settings::get_by_quiz_id($quiz2->id);
        $this->assertEquals(\quizaccess_seb\settings_provider::USE_SEB_UPLOAD_CONFIG, $copy->get('requiresafeexambrowser'));
        $cm2 = get_coursemodule_from_instance('quiz', $quiz2->id);
        $this->assertNotNull(\quizaccess_seb\settings_provider::get_module_context_sebconfig_file($cm2->id));
        // SEB writes each quiz's own start address into the config, so it opens the right quiz.
        $this->assertStringContainsString('/mod/quiz/view.php?id=' . $cm2->id, $copy->get_config());
        $this->assertNotEmpty($copy->get_config_key());
        // Quiz 3 no longer uses SEB.
        $this->assertFalse(\quizaccess_seb\seb_quiz_settings::get_by_quiz_id($quiz3->id));
        $this->assertFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz3->id]));
    }

    public function test_seb_validation(): void {
        global $DB;
        [$course, $quiz1, $quiz2] = $this->setup_course();
        $quiz3 = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $this->setAdminUser();
        $this->enable_seb($quiz1, \quizaccess_seb\settings_provider::USE_SEB_CLIENT_CONFIG);
        // Quiz 3 has an attempt, so its SEB settings are locked.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record('quiz_attempts', ['quiz' => $quiz3->id, 'userid' => $student->id, 'attempt' => 1,
            'uniqueid' => 999999, 'layout' => '', 'currentpage' => 0, 'preview' => 0, 'state' => 'inprogress',
            'timestart' => time(), 'timefinish' => 0, 'timemodified' => time(), 'timemodifiedoffline' => 0,
            'timecheckstate' => null, 'sumgrades' => null]);

        $quizzes = updater::get_quizzes($course);
        $this->assertTrue($quizzes[$quiz3->id]['quiz']->seblocked);
        $shown = $this->shown($quizzes);
        $submitted = $shown;
        $submitted[$quiz1->id]['seb'] = 'q' . $quiz1->id;
        $submitted[$quiz2->id]['seb'] = 'q' . $quiz3->id;
        $submitted[$quiz3->id]['seb'] = 'q' . $quiz1->id;
        [$changes, $errors] = updater::collect_changes($quizzes, $submitted, $shown);
        $this->assertSame([], $changes);
        // Itself, and a quiz without SEB, aren't valid sources; quiz 3 is locked.
        $this->assertArrayHasKey('seb', $errors[$quiz1->id]);
        $this->assertArrayHasKey('seb', $errors[$quiz2->id]);
        $this->assertSame(get_string('errorseblocked', 'local_quizbulkedit'), $errors[$quiz3->id]['seb']);
    }
}
