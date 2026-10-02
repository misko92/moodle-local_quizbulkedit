@local @local_quizbulkedit @javascript
Feature: Quiz passwords typed on a student's computer stay hidden
  In order to keep my quiz passwords secret
  As a teacher typing the password on a student's computer
  I need students to be unable to reveal it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext |
      | Test questions   | truefalse | TF1  | First        |
    And the following "activities" exist:
      | activity | name   | course | quizpassword |
      | quiz     | Quiz 1 | C1     | Secret123    |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |

  Scenario: No reveal button, and the typed password is gone after pressing Back
    Given I am on the "Quiz 1" "quiz activity" page logged in as student1
    When I press "Attempt quiz"
    Then "Reveal" "link" should not be visible
    And I set the field "Quiz password" to "Secret123"
    # What a real browser does when the quiz starts and the page goes into its Back cache.
    And the browser stores the page in its back/forward cache
    And the field "Quiz password" matches value ""
    # And when the student presses Back: the page reloads, so the pop-up is gone.
    And I set the field "Quiz password" to "Secret123"
    And the browser restores the page from its back/forward cache
    And I should see "Attempt quiz"
    And "Start attempt" "button" should not be visible
    # The real flow still works.
    And I press "Attempt quiz"
    And I set the field "Quiz password" to "Secret123"
    And I press "Start attempt"
    And I should see "First"

  Scenario: The protection can be switched off
    Given the following config values are set as admin:
      | passwordguard | 0 | local_quizbulkedit |
    And I am on the "Quiz 1" "quiz activity" page logged in as student1
    When I press "Attempt quiz"
    Then "Reveal" "link" should be visible
    And I set the field "Quiz password" to "Secret123"
    And the browser stores the page in its back/forward cache
    And the field "Quiz password" matches value "Secret123"
