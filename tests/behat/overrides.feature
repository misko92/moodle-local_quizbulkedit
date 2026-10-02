@local @local_quizbulkedit @javascript
Feature: Extra time and other overrides on many quizzes at once
  In order to give students their accommodations quickly
  As a teacher
  I need to add overrides to many quizzes at once

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name        | course | timelimit |
      | quiz     | Unit 1 test | C1     | 3600      |
      | quiz     | Unit 2 test | C1     | 1800      |
      | quiz     | Practice    | C1     | 0         |

  Scenario: Give a student time and a half on every quiz, then remove it
    Given I am on the "Course 1" course page logged in as teacher1
    And I navigate to "Bulk edit quizzes" in current page administration
    When I click on "Extra time & overrides" "link"
    And I set the field "Students" to "Sam Student"
    And I set the field "All 3 quizzes in this course" to "1"
    And I set the field "Time limit" to "Multiply the quiz's time limit"
    And I set the field "Multiply by" to "1.5"
    And I set the field "Reason" to "IEP: time and a half"
    And I press "Preview"
    Then I should see "2 override(s) to save or remove, 1 skipped"
    And I should see "The quiz has no time limit to change."
    And I should see "Time limit: 1 hour 30 mins"
    And I press "Apply"
    And I should see "2 override(s) saved, 0 removed."
    And I should see "Time limit: 45 mins"
    And I should see "IEP: time and a half"
    And I am on the "Unit 1 test" "mod_quiz > User overrides" page
    And I should see "1 hour 30 mins"
    # Remove them again.
    And I am on the "Course 1" course page
    And I navigate to "Bulk edit quizzes" in current page administration
    And I click on "Extra time & overrides" "link"
    And I set the field "Action" to "Remove overrides"
    And I set the field "Students" to "Sam Student"
    And I set the field "All 3 quizzes in this course" to "1"
    And I press "Preview"
    And I press "Apply"
    And I should see "0 override(s) saved, 2 removed."
    And I should see "No quiz in this course has overrides."
