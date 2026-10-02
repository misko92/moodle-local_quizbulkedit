# Quiz bulk edit (local_quizbulkedit)

Edit the settings of every quiz in a course on one page, instead of opening each quiz's settings one at a time.

In a course, open **More → Bulk edit quizzes**. Each quiz is one row of the table, with these settings:

| Setting | Notes |
|---|---|
| Visibility | Shown / Hidden. Needs `moodle/course:activityvisibility`. |
| Password | |
| Network address | "Require network address": comma-separated IPs, partial addresses (`10.1.`), ranges (`10.0.0.1-50`) or CIDR (`192.168.10.0/24`). Empty = any. |
| Open / Close | Empty = no restriction. |
| Time limit | In minutes, 0 = no limit. |
| Attempts | 0 = unlimited. |
| Grading method | Changing it regrades the quiz, like the normal settings form does. |
| Review options | **View** shows the quiz's review options grid (**View all** in the header expands every quiz). Copy all review options from another quiz in the course with "Same as …". |

- **Filter** by keyword (quiz or section name). Select all and Apply to selected only affect the quizzes shown; the filter is kept after saving.
- Edit any cell, or tick quizzes and use the toolbar to **Apply to selected** a value for one setting.
- **Random password for each selected** gives every ticked quiz its own random 6-character password.
- Only the values you changed are saved, so the page never overwrites edits made elsewhere after you opened it.
- If any value is invalid, nothing is saved and the errors are shown in place.

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
