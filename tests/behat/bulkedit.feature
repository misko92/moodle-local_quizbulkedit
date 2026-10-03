@local @local_quizbulkedit @javascript
Feature: Bulk edit quiz settings
  In order to save time
  As a teacher
  I need to change settings of many quizzes on one page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | quizpassword |
      | quiz     | Quiz A | C1     | aaa          |
      | quiz     | Quiz B | C1     | bbb          |
      | quiz     | Quiz C | C1     | ccc          |

  Scenario: Set the same password on selected quizzes
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select Quiz A" "checkbox"
    And I click on "Select Quiz B" "checkbox"
    And I set the field "Setting" to "Password"
    And I set the field "Value" to "secret"
    And I press "Apply to selected"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "2 quiz(zes) updated."
    And the field "Password: Quiz A" matches value "secret"
    And the field "Password: Quiz B" matches value "secret"
    And the field "Password: Quiz C" matches value "ccc"

  Scenario: Give every quiz its own random password
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select all quizzes" "checkbox"
    And I set the field "Setting" to "Password"
    And I press "Random password for each selected"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "3 quiz(zes) updated."
    And the field "Password: Quiz A" does not match value "aaa"
    And the field "Password: Quiz C" does not match value "ccc"

  Scenario: Hide quizzes and copy review options
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select Quiz B" "checkbox"
    And I click on "Select Quiz C" "checkbox"
    And I set the field "Setting" to "Visibility"
    And I set the field "Value" to "Hidden"
    And I press "Apply to selected"
    And I set the field "Setting" to "Review options"
    And I set the field "Value" to "Same as Quiz A"
    And I press "Apply to selected"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "2 quiz(zes) updated."
    And the field "Visibility: Quiz A" matches value "Shown"
    And the field "Visibility: Quiz B" matches value "Hidden"
    And the field "Visibility: Quiz C" matches value "Hidden"

  Scenario: Filter by keyword, then bulk changes only touch the quizzes shown
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I set the field "Filter" to "quiz b"
    Then I should see "Showing 1 of 3 quizzes"
    And "Select Quiz A" "checkbox" should not be visible
    And I click on "Show review options of Quiz B" "button"
    And I should see "Review options of Quiz B"
    And I click on "Select all quizzes" "checkbox"
    And I set the field "Setting" to "Password"
    And I set the field "Value" to "onlyb"
    And I press "Apply to selected"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And the field "Filter" matches value "quiz b"
    And I set the field "Filter" to ""
    And the field "Password: Quiz A" matches value "aaa"
    And the field "Password: Quiz B" matches value "onlyb"
    And the field "Password: Quiz C" matches value "ccc"

  Scenario: Edit review options in the grid
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Show review options of Quiz B" "button"
    And I set the field "Right answer (Immediately after the attempt): Quiz B" to "1"
    And I set the field "The attempt (Immediately after the attempt): Quiz B" to "0"
    # Like the quiz settings form: no attempt, so no right answer either.
    Then the "Right answer (Immediately after the attempt): Quiz B" "checkbox" should be disabled
    And the field "Right answer (Immediately after the attempt): Quiz B" matches value "0"
    And the "The attempt (During the attempt): Quiz B" "checkbox" should be disabled
    And I set the field "The attempt (Immediately after the attempt): Quiz B" to "1"
    And I set the field "Right answer (Immediately after the attempt): Quiz B" to "1"
    And I set the field "Right answer (Later, while the quiz is still open): Quiz B" to "0"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And I click on "Show review options of Quiz B" "button"
    And the field "Right answer (Immediately after the attempt): Quiz B" matches value "1"
    And the field "Right answer (Later, while the quiz is still open): Quiz B" matches value "0"
    And I click on "Show review options of Quiz C" "button"
    And I set the field "Copy review options to Quiz C from" to "Same as Quiz B"
    And the field "Right answer (Later, while the quiz is still open): Quiz C" matches value "0"

  Scenario: Choose columns, and the choice is remembered
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    Then "When time expires: Quiz A" "field" should not be visible
    And I press "Columns"
    And I click on "When time expires" "checkbox"
    And I click on "Password" "checkbox"
    And I press "Columns"
    And "Password: Quiz A" "field" should not be visible
    And I set the field "When time expires: Quiz A" to "Attempts must be submitted before time expires, or they are not counted"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    # The column choice survives the reload.
    And the field "When time expires: Quiz A" matches value "Attempts must be submitted before time expires, or they are not counted"
    And "Password: Quiz A" "field" should not be visible
    And I press "Columns"
    And I press "Reset to default columns"
    And "When time expires: Quiz A" "field" should not be visible
    And "Password: Quiz A" "field" should be visible

  Scenario: Show only the quizzes with unsaved changes
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I set the field "Password: Quiz B" to "changed"
    And I click on "Show changed quizzes only" "checkbox"
    Then "Select Quiz A" "checkbox" should not be visible
    And "Select Quiz C" "checkbox" should not be visible
    And "Select Quiz B" "checkbox" should be visible

  Scenario: Rename quizzes and set grades
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I press "Columns"
    And I click on "Quiz name" "checkbox"
    And I click on "Maximum grade" "checkbox"
    And I click on "Grade to pass" "checkbox"
    And I press "Columns"
    And I set the field "Quiz name: Quiz A" to "Unit 1 test"
    And I set the field "Maximum grade: Quiz A" to "20"
    And I set the field "Grade to pass: Quiz A" to "12"
    And I set the field "Grade to pass: Quiz B" to "150"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "Nothing was saved"
    And I should see "The grade to pass can not be greater than the maximum possible grade 100"
    And I set the field "Grade to pass: Quiz B" to "5"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "2 quiz(zes) updated."
    And the field "Quiz name: Unit 1 test" matches value "Unit 1 test"
    And the field "Maximum grade: Unit 1 test" matches value "20"
    And the field "Grade to pass: Unit 1 test" matches value "12"
    And I am on the "Unit 1 test" "quiz activity editing" page
    And the field "Grade to pass" matches value "12.00"

  Scenario: Copy Safe Exam Browser settings, and turn them off
    Given I am on the "Quiz A" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Use SEB client config"
    And I press "Save and return to course"
    When I navigate to "Bulk edit quizzes" in current page administration
    Then the field "Safe Exam Browser: Quiz A" matches value "Yes – Use SEB client config"
    And I set the field "Safe Exam Browser: Quiz B" to "Same as Quiz A (Yes – Use SEB client config)"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And the field "Safe Exam Browser: Quiz B" matches value "Yes – Use SEB client config"
    And I set the field "Safe Exam Browser: Quiz A" to "No"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And the field "Safe Exam Browser: Quiz A" matches value "No"
    And I am on the "Quiz B" "quiz activity editing" page
    And I expand all fieldsets
    And the field "Require the use of Safe Exam Browser" matches value "Yes – Use SEB client config"

  Scenario: Review the changes before they are saved
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I set the field "Password: Quiz B" to "newpass"
    And I set the field "Attempts: Quiz C" to "2"
    And I press "Save changes"
    Then I should see "2 change(s) to 2 quiz(zes):" in the "Review changes" "dialogue"
    And I should see "Password: bbb → newpass" in the "Review changes" "dialogue"
    And I should see "Attempts: 0 → 2" in the "Review changes" "dialogue"
    # Cancel: nothing is saved, the edits stay on the page.
    And I click on "Cancel" "button" in the "Review changes" "dialogue"
    And the field "Password: Quiz B" matches value "newpass"
    And I am on the "Course 1" course page
    And I navigate to "Bulk edit quizzes" in current page administration
    And the field "Password: Quiz B" matches value "bbb"

  Scenario: Open now and Close now
    Given the following "activities" exist:
      | activity | name   | course | visible |
      | quiz     | Quiz D | C1     | 0       |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select Quiz D" "checkbox"
    And I press "Open now"
    # Opening a hidden quiz also shows it.
    Then I should see "Visibility: Hidden → Shown" in the "Review changes" "dialogue"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And the field "Open: Quiz D" does not match value ""
    And the field "Visibility: Quiz D" matches value "Shown"
    And the field "Close: Quiz D" matches value ""
    And I click on "Select Quiz D" "checkbox"
    And I press "Close now"
    And I should see "also applies to attempts in progress" in the "Review changes" "dialogue"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And the field "Close: Quiz D" does not match value ""
    And the field "Visibility: Quiz D" matches value "Shown"

  Scenario: Copy all settings of one quiz to others
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I set the field "Attempts: Quiz A" to "3"
    And I set the field "Time limit (min): Quiz A" to "45"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I click on "Select Quiz B" "checkbox"
    And I click on "Select Quiz C" "checkbox"
    And I set the field "Setting" to "All settings (except name, visibility and dates)"
    And I set the field "Value" to "Same as Quiz A"
    And I press "Apply to selected"
    Then the field "Password: Quiz B" matches value "aaa"
    And the field "Attempts: Quiz C" matches value "3"
    And the field "Time limit (min): Quiz C" matches value "45"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "2 quiz(zes) updated."
    And the field "Password: Quiz C" matches value "aaa"
    And "Select Quiz A" "checkbox" should be visible

  Scenario: Turn Safe Exam Browser on when no quiz uses it yet
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select Quiz A" "checkbox"
    And I click on "Select Quiz B" "checkbox"
    And I set the field "Setting" to "Safe Exam Browser"
    And I set the field "Value" to "Yes – Use SEB client config"
    And I press "Apply to selected"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "2 quiz(zes) updated."
    And the field "Safe Exam Browser: Quiz A" matches value "Yes – Use SEB client config"
    And the field "Safe Exam Browser: Quiz C" matches value "No"
    And I set the field "Safe Exam Browser: Quiz C" to "Yes – Configure manually (default settings)"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I am on the "Quiz C" "quiz activity editing" page
    And I expand all fieldsets
    And the field "Require the use of Safe Exam Browser" matches value "Yes – Configure manually"

  Scenario: Close now clears an open date that is still in the future
    Given the following "activities" exist:
      | activity | name   | course | timeopen     |
      | quiz     | Quiz E | C1     | ##tomorrow## |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Select Quiz E" "checkbox"
    And I press "Close now"
    # Close must be after open, and the quiz is closed either way, so the future open date is cleared.
    Then I should see "Open:" in the "Review changes" "dialogue"
    And I should see "(none)" in the "Review changes" "dialogue"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "1 quiz(zes) updated."
    And the field "Open: Quiz E" matches value ""
    And the field "Close: Quiz E" does not match value ""
