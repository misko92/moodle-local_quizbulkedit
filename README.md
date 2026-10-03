# Quiz bulk edit (local_quizbulkedit)

Edit the settings of every quiz in a course on one page, instead of opening each quiz's settings one at a time.

In a course, open **More → Bulk edit quizzes**. Each quiz is one row of the table. Pick the columns you want with **Columns** (grouped like the quiz settings form; remembered per user):

| Group | Settings |
|---|---|
| General | Quiz name (renames everywhere, incl. gradebook and calendar); visibility (needs `moodle/course:activityvisibility`) |
| Extra restrictions | Password; network address (comma-separated IPs, partial addresses, ranges or CIDR); Safe Exam Browser (turn on with SEB client config, a site template or manual default settings; turn off; or copy another quiz's whole SEB setup including an uploaded config file; locked once a quiz has attempts, and each SEB mode needs its own capability, as in the quiz settings form); enforced delays between attempts |
| Timing | Open, due date (Moodle 5.3+; must be after open and no later than close), close, time limit, when time expires, grace period (checked against the site minimum, as the settings form does) |
| Grade | Maximum grade (rescales existing grades, as the quiz Questions page does); grade to pass (same checks as the settings form); attempts; grading method (changing it regrades) |
| Layout | Navigation method |
| Question behaviour | Shuffle within questions, allow redo within an attempt, each attempt builds on last |
| Review options | **Show** opens an editable grid (**Show all** opens every quiz). The quiz settings form's rules apply: options grey out when they depend on another, when the question behaviour doesn't use them during the attempt, or (after close) when the quiz has no close date. "Copy from…" fills the grid from another quiz. |
| Appearance | Show the user's picture, decimal places in grades and in question marks, show blocks |

Times are entered in minutes. The quiz name and select box stay in view while scrolling a wide table sideways.

- **Show changed quizzes only** narrows the list to quizzes with unsaved edits.
- **Save changes** first shows a summary of every change (old → new, per quiz), with warnings where a change has knock-on
  effects; nothing is saved until you confirm.
- **Open now** / **Close now** set the selected quizzes' open or close date to the current minute and go straight to that
  summary. Open now also shows a hidden quiz; Close now clears an open date that isn't before now, and a due date still to
  come.
- **All settings (except name, visibility and dates)** in the toolbar copies one quiz's whole setup, including review
  options and Safe Exam Browser, to the selected quizzes.
- **Filter** by keyword (quiz or section name). Select all and Apply to selected only affect the quizzes shown; the filter is kept after saving.
- Edit any cell, or tick quizzes and use the toolbar to **Apply to selected** a value for one setting.
- **Random password for each selected** gives every ticked quiz its own random 6-character password.
- Only the values you changed are saved, so the page never overwrites edits made elsewhere after you opened it.
- If any value is invalid, nothing is saved and the errors are shown in place.

## Extra time & overrides

**Bulk edit quizzes → Extra time & overrides** adds, updates or removes standard quiz overrides for chosen students
and/or groups on all (or chosen) quizzes in the course, e.g. an accommodation of time and a half:

- Time limit: multiply the quiz's own limit (e.g. ×1.5), add minutes, or set it; attempts; open date; close date (set,
  or add minutes to the quiz's); due date on Moodle 5.3+ (set, or add minutes to the quiz's); password; and an optional
  reason saved with each override.
- New values are merged into an existing override for the same student or group, so its other settings (including a
  due date) are kept.
- **Preview** lists every student/group × quiz with the current and new override (and why any are skipped, e.g. a quiz
  with no time limit); nothing is saved until **Apply**.
- Saving uses mod_quiz's override manager (validation, calendar events, attempts in progress, logging), in one
  transaction. Needs `mod/quiz:manageoverrides`; without `moodle/site:accessallgroups` only your groups are offered.
- The page also lists every current override in the course.

## Protecting quiz passwords on student computers

When a teacher types the quiz password into a student's "Start attempt" pop-up, the browser's back/forward cache can
keep that page with the password still typed in, and the password field has a Reveal (eye) button. On student quiz
pages this plugin hides that button, empties the field as the page is left, and reloads the page if it comes back
from the cache. The teacher's own quiz settings page is not affected.

Site administration → Plugins → Local plugins → Quiz bulk edit → *Protect quiz passwords on student computers*
(on by default).

## How it saves

Settings are written straight to the `quiz` table rather than through `quiz_update_instance()`. That function expects
the full quiz settings form, and given a partial record it resets the review options and deletes the overall feedback.
The plugin reproduces the side effects instead: calendar events, deadlines of attempts in progress, regrading,
preview cleanup, the course cache and the `course_module_updated` log event.

## Permissions

Uses `moodle/course:manageactivities` (course and each quiz). No capabilities of its own.

## Requirements

Moodle 5.2 – 5.3.

## License

GNU GPL v3 or later.
