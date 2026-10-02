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
    And I click on "View review options of Quiz B" "button"
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
