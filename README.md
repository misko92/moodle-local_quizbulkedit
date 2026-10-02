# Quiz bulk edit (local_quizbulkedit)

Edit the settings of every quiz in a course on one page, instead of opening each quiz's settings one at a time.

In a course, open **More → Bulk edit quizzes**. Each quiz is one row of the table, with these settings:

Pick the columns you want with **Columns** (grouped like the quiz settings form; remembered per user):

| Group | Settings |
|---|---|
| General | Visibility (needs `moodle/course:activityvisibility`) |
| Extra restrictions | Password; network address (comma-separated IPs, partial addresses, ranges or CIDR); enforced delays between attempts |
| Timing | Open, close, time limit, when time expires, grace period (checked against the site minimum, as the settings form does) |
| Grade | Attempts, grading method (changing it regrades) |
| Layout | Navigation method |
| Question behaviour | Shuffle within questions, allow redo within an attempt, each attempt builds on last |
| Review options | **Show** opens an editable grid (**Show all** opens every quiz). The quiz settings form's rules apply: options grey out when they depend on another, when the question behaviour doesn't use them during the attempt, or (after close) when the quiz has no close date. "Copy from…" fills the grid from another quiz. |
| Appearance | Show the user's picture, decimal places in grades and in question marks, show blocks |

Times are entered in minutes. The quiz name and select box stay in view while scrolling a wide table sideways.

- **Show changed quizzes only** narrows the list to quizzes with unsaved edits.
- **Filter** by keyword (quiz or section name). Select all and Apply to selected only affect the quizzes shown; the filter is kept after saving.
- Edit any cell, or tick quizzes and use the toolbar to **Apply to selected** a value for one setting.
- **Random password for each selected** gives every ticked quiz its own random 6-character password.
- Only the values you changed are saved, so the page never overwrites edits made elsewhere after you opened it.
- If any value is invalid, nothing is saved and the errors are shown in place.

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

Moodle 5.0 – 5.2.

## License

GNU GPL v3 or later.
