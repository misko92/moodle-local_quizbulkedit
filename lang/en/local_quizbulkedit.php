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

/**
 * Language strings.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allsettings'] = 'All settings (except name, visibility and dates)';
$string['applytoselected'] = 'Apply to selected';
$string['attemptonlast'] = 'Each attempt builds on last';
$string['attempts'] = 'Attempts';
$string['attempts_hint'] = '0 = unlimited';
$string['backtobulkedit'] = 'Back to Bulk edit quizzes';
$string['bulkeditquizzes'] = 'Bulk edit quizzes';
$string['canredoquestions'] = 'Allow redo within an attempt';
$string['changedonly'] = 'Show changed quizzes only';
$string['changessaved'] = '{$a} quiz(zes) updated.';
$string['clearvalue'] = 'Leave the value empty to clear it (no password / no network or date restriction).';
$string['closenow'] = 'Close now';
$string['columns'] = 'Columns';
$string['columnsdefault'] = 'Reset to default columns';
$string['confirmattempts'] = 'Changing close dates, time limits or what happens when time expires also applies to attempts in progress.';
$string['confirmgrade'] = 'Changing the maximum grade rescales grades students already have.';
$string['confirmnone'] = '(none)';
$string['confirmreview'] = 'Review options changed';
$string['confirmsave'] = 'Save';
$string['confirmsummary'] = '{$a->settings} change(s) to {$a->quizzes} quiz(zes):';
$string['confirmtitle'] = 'Review changes';
$string['decimalpoints'] = 'Decimal places in grades';
$string['delay1'] = 'Delay 1st–2nd attempt (min)';
$string['delay1_hint'] = '0 = none';
$string['delay2'] = 'Delay later attempts (min)';
$string['delay2_hint'] = '0 = none';
$string['duedate'] = 'Due date';
$string['editsettings'] = 'Edit all settings for {$a}';
$string['errorattempts'] = 'Attempts must be a whole number, 0 or more.';
$string['errorchoice'] = 'Invalid choice.';
$string['errorcloseopen'] = 'The close date must be after the open date.';
$string['errordate'] = 'Invalid date.';
$string['errorgrade'] = 'Must be a number, 0 or more.';
$string['errorgradepass'] = 'Must be a number, 0 or more.';
$string['errorminutes'] = 'Must be a number of minutes, 0 or more.';
$string['errorname'] = 'The name must not be empty, and at most 255 characters.';
$string['errornogradeitem'] = 'This quiz has no gradebook item, so it has no grade to pass.';
$string['errorpassword'] = 'The password must be 255 characters or fewer.';
$string['errorreview'] = 'Invalid review options.';
$string['errorseblocked'] = 'Safe Exam Browser settings can\'t be changed once the quiz has attempts.';
$string['errorsebpermission'] = 'You are not allowed to change the Safe Exam Browser settings of this quiz.';
$string['errorsebsource'] = 'Choose a quiz that uses Safe Exam Browser.';
$string['errorsubnet'] = 'Use IP addresses separated by commas, e.g. 192.168.10.0/24, 10.1.';
$string['errorvisibility'] = 'You are not allowed to show or hide this quiz.';
$string['field'] = 'Setting';
$string['filter'] = 'Filter';
$string['filtercount'] = 'Showing {$a->shown} of {$a->total} quizzes';
$string['filterplaceholder'] = 'Quiz or section name';
$string['fixerrors'] = 'Nothing was saved. Please fix the highlighted values and save again.';
$string['graceperiod'] = 'Grace period (min)';
$string['graceperiod_hint'] = 'if submitting in grace period';
$string['grade'] = 'Maximum grade';
$string['grade_hint'] = 'rescales existing grades';
$string['grademethod'] = 'Grading method';
$string['gradepass'] = 'Grade to pass';
$string['group_appearance'] = 'Appearance';
$string['group_behaviour'] = 'Question behaviour';
$string['group_general'] = 'General';
$string['group_grade'] = 'Grade';
$string['group_layout'] = 'Layout';
$string['group_restrictions'] = 'Extra restrictions';
$string['group_review'] = 'Review options';
$string['group_timing'] = 'Timing';
$string['name'] = 'Quiz name';
$string['navmethod'] = 'Navigation method';
$string['nochanges'] = 'No changes to save.';
$string['noquizzes'] = 'There are no quizzes in this course that you can edit.';
$string['opennow'] = 'Open now';
$string['ovaction'] = 'Action';
$string['ovactiondelete'] = 'Remove overrides';
$string['ovactionsave'] = 'Add or update overrides';
$string['ovaddminutes'] = 'Add minutes to the quiz\'s time limit';
$string['ovaddtoclose'] = 'Add minutes to the quiz\'s close date';
$string['ovaddtodue'] = 'Add minutes to the quiz\'s due date';
$string['ovallquizzes'] = 'All {$a} quizzes in this course';
$string['ovapply'] = 'Apply';
$string['ovattempts'] = 'Attempts';
$string['ovattemptsvalue'] = 'Attempts allowed (0 = unlimited)';
$string['ovchoosequizzes'] = 'Quizzes';
$string['ovcolaction'] = 'Action';
$string['ovcolafter'] = 'New override';
$string['ovcolbefore'] = 'Current override';
$string['ovcolnote'] = 'Note';
$string['ovcolquiz'] = 'Quiz';
$string['ovcolsettings'] = 'Settings';
$string['ovcolwho'] = 'Student or group';
$string['ovcurrent'] = 'Current overrides in this course';
$string['ovcurrentnone'] = 'No quiz in this course has overrides.';
$string['ovdocreate'] = 'Create';
$string['ovdodelete'] = 'Remove';
$string['ovdone'] = '{$a->saved} override(s) saved, {$a->removed} removed.';
$string['ovdoskip'] = 'Skip';
$string['ovdoupdate'] = 'Update';
$string['ovduedate'] = 'Due';
$string['ovduedatevalue'] = 'Due';
$string['overduehandling'] = 'When time expires';
$string['overrides'] = 'Extra time & overrides';
$string['overrides_desc'] = 'Give students or groups their own time limit, attempts, dates or password on many quizzes at once, for example extra time as an accommodation. These are standard quiz overrides: each also shows on its quiz\'s Overrides page.';
$string['ovfactor'] = 'Multiply by';
$string['ovgroup'] = 'Group: {$a}';
$string['ovgroups'] = 'Groups';
$string['ovkeep'] = 'Don\'t change';
$string['ovminutes'] = 'Minutes';
$string['ovmultiply'] = 'Multiply the quiz\'s time limit';
$string['ovneedquiz'] = 'Choose at least one quiz.';
$string['ovneedsetting'] = 'Choose at least one setting to change.';
$string['ovneedwho'] = 'Choose at least one student or group.';
$string['ovnochange'] = 'Already set.';
$string['ovnoclose'] = 'The quiz has no close date to extend.';
$string['ovnodue'] = 'The quiz has no due date to extend.';
$string['ovnone'] = 'None';
$string['ovnoneselected'] = 'None selected';
$string['ovnooverride'] = 'No override to remove.';
$string['ovnotimelimit'] = 'The quiz has no time limit to change.';
$string['ovpassword'] = 'Password';
$string['ovpasswordvalue'] = 'Password';
$string['ovpositive'] = 'Must be more than 0.';
$string['ovpreview'] = 'Preview';
$string['ovpreviewheading'] = 'Preview';
$string['ovpreviewsummary'] = '{$a->todo} override(s) to save or remove, {$a->skipped} skipped. Nothing has been saved yet: check the table, then press Apply.';
$string['ovquizzes'] = 'Quizzes';
$string['ovreason'] = 'Reason';
$string['ovreason_help'] = 'An optional note saved with each override, e.g. "IEP: time and a half". It shows on the quiz\'s Overrides page.';
$string['ovsameasquiz'] = 'Same as the quiz\'s own settings, so no override is needed.';
$string['ovset'] = 'Set to';
$string['ovsetminutes'] = 'Set to (minutes)';
$string['ovstudents'] = 'Students';
$string['ovtimeclose'] = 'Closes';
$string['ovtimeclosevalue'] = 'Closes';
$string['ovtimelimit'] = 'Time limit';
$string['ovtimeopen'] = 'Opens';
$string['ovtimeopenvalue'] = 'Opens';
$string['ovwhat'] = 'Settings';
$string['ovwho'] = 'Students and groups';
$string['password'] = 'Password';
$string['passwordguard'] = 'Protect quiz passwords on student computers';
$string['passwordguard_desc'] = 'On student quiz pages, hide the Reveal (eye) button of the quiz password prompt, and clear the typed password when the page is left, so that pressing Back after a teacher has entered the password cannot show it.';
$string['pluginname'] = 'Quiz bulk edit';
$string['privacy:metadata'] = 'The Quiz bulk edit plugin does not store any personal data.';
$string['questiondecimalpoints'] = 'Decimal places in marks for questions';
$string['quiz'] = 'Quiz';
$string['randompasswords'] = 'Random password for each selected';
$string['review'] = 'Review options';
$string['review_hint'] = 'Show to view or edit';
$string['reviewcheckbox'] = '{$a->option} ({$a->when}): {$a->quiz}';
$string['reviewcopy'] = 'Same as {$a}';
$string['reviewcopyfor'] = 'Copy review options to {$a} from';
$string['reviewcopyfrom'] = 'Copy from…';
$string['reviewfor'] = 'Review options of {$a}';
$string['savechanges'] = 'Save changes';
$string['seb'] = 'Safe Exam Browser';
$string['seb_hint'] = 'turn on or off, or copy from a quiz';
$string['seblocked'] = 'Locked: the quiz has attempts';
$string['sebmanualdefaults'] = '{$a} (default settings)';
$string['select'] = 'Select {$a}';
$string['selectall'] = 'Select all quizzes';
$string['selectfirst'] = 'Select one or more quizzes first.';
$string['showallreview'] = 'Show all';
$string['showblocks'] = 'Show blocks during attempts';
$string['showreview'] = 'Show';
$string['showreviewfor'] = 'Show review options of {$a}';
$string['showuserpicture'] = 'Show the user\'s picture';
$string['shuffleanswers'] = 'Shuffle within questions';
$string['subnet'] = 'Network address';
$string['subnet_hint'] = 'e.g. 192.168.10.0/24, empty = any';
$string['timeclose'] = 'Close';
$string['timelimit'] = 'Time limit (min)';
$string['timelimit_hint'] = '0 = no limit';
$string['timeopen'] = 'Open';
$string['value'] = 'Value';
$string['visible'] = 'Visibility';
$string['visible_hide'] = 'Hidden';
$string['visible_show'] = 'Shown';
