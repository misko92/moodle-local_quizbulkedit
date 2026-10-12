@local @local_quizbulkedit @javascript
Feature: Quiz, section and group names show as typed
  In order to recognise my quizzes and groups
  As a teacher
  I need names with special characters like & to show correctly, not as "&amp;"

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname | initsections |
      | Course 1 | C1        | 1            |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "groups" exist:
      | name      | course | idnumber |
      | Lab A & B | C1     | G1       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
    And the following "activities" exist:
      | activity | name         | course | section | timelimit |
      | quiz     | Moles & Mass | C1     | 1       | 3600      |
    And I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I edit the section "1" and I fill the form with:
      | Section name | Atoms & Ions |

  Scenario: Names on the bulk edit page
    When I navigate to "Bulk edit quizzes" in current page administration
    Then I should see "Moles & Mass" in the ".local-quizbulkedit-table" "css_element"
    And I should see "Atoms & Ions" in the ".local-quizbulkedit-table" "css_element"
    And I should not see "&amp;"

  Scenario: Names on the overrides page
    When I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Extra time & overrides" "link"
    And I set the field "Groups" to "Lab A & B"
    And I set the field "All 1 quizzes in this course" to "1"
    And I set the field "Time limit" to "Multiply the quiz's time limit"
    And I set the field "Multiply by" to "1.5"
    And I press "Preview"
    Then I should see "Moles & Mass" in the "table.generaltable" "css_element"
    And I should see "Lab A & B" in the "table.generaltable" "css_element"
    And I should not see "&amp;"
    And I press "Apply"
    And I should see "Group: Lab A & B"
    And I should not see "&amp;"
