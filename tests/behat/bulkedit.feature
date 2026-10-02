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
    Then I should see "Nothing was saved"
    And I should see "The grade to pass can not be greater than the maximum possible grade 100"
    And I set the field "Grade to pass: Quiz B" to "5"
    And I press "Save changes"
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
    And I should see "1 quiz(zes) updated."
    And the field "Safe Exam Browser: Quiz B" matches value "Yes – Use SEB client config"
    And I set the field "Safe Exam Browser: Quiz A" to "No"
    And I press "Save changes"
    And the field "Safe Exam Browser: Quiz A" matches value "No"
    And I am on the "Quiz B" "quiz activity editing" page
    And I expand all fieldsets
    And the field "Require the use of Safe Exam Browser" matches value "Yes – Use SEB client config"
