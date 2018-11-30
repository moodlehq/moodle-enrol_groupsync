@enrol @enrol_groupsync
Feature: Cohort membership is one-way synchronised with the group membership
  In order to automatically add cohort members to the course group members
  As a teacher
  I can set up groupsync enrolments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Studie    | One      | student1@example.com |
      | student2 | Studie    | Two      | student2@example.com |
      | student3 | Studie    | Three    | student3@example.com |
      | student4 | Studie    | Four     | student4@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course 001 | C001      |
    And the following "groups" exist:
      | name          | course | idnumber |
      | Groupcourse 1 | C001   | G1       |
      | Groupcourse 2 | C001   | G2       |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Cohort members to group" "table_row"
    And I am on homepage
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | Even numbered users |
      | Context     | System              |
      | Cohort ID   | Even                |
    And I press "Save changes"
    And I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | Odd numbered users  |
      | Context     | System              |
      | Cohort ID   | Odd                 |
    And I press "Save changes"
    And I add "Studie One (student1@example.com)" user to "Even" cohort members
    And I add "Studie Two (student2@example.com)" user to "Odd" cohort members

  Scenario: Adding groupsync enrolment instance populates the group members
    Given I am on "Course 001" course homepage
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C001   | student |
      | student2 | C001   | student |
      | student3 | C001   | student |
    When I add "Cohort members to group" enrolment method with:
      | Cohort       | Even numbered users |
      | Add to group | Groupcourse 1       |
    And I am on "Course 001" course homepage
    And I add "Cohort members to group" enrolment method with:
      | Cohort       | Odd numbered users  |
      | Add to group | Groupcourse 2       |
    And I am on "Course 001" course homepage
    And I navigate to "Users > Enrolled users" in current page administration
    Then I should see "Groupcourse 1" in the "Studie One" "table_row"
    And I should see "Groupcourse 2" in the "Studie Two" "table_row"
    And I should not see "Groupcourse 1" in the "Studie Three" "table_row"
    And I should not see "Groupcourse 2" in the "Studie Three" "table_row"

  Scenario: Enrolling cohort member puts them into the group
    Given I am on "Course 001" course homepage
    When I add "Cohort members to group" enrolment method with:
      | Cohort       | Odd numbered users  |
      | Add to group | Groupcourse 2       |
    And I am on "Course 001" course homepage
    And I enrol "Studie One" user as "Student"
    And I am on "Course 001" course homepage
    And I enrol "Studie Two" user as "Student"
    And I am on "Course 001" course homepage
    And I navigate to "Users > Enrolled users" in current page administration
    Then I should see "Groupcourse 2" in the "Studie Two" "table_row"
    And I should not see "Groupcourse 1" in the "Studie Two" "table_row"
    And I should not see "Groupcourse 1" in the "Studie One" "table_row"
    And I should not see "Groupcourse 2" in the "Studie One" "table_row"

  Scenario: Becoming cohort member leads to becoming group member
    Given I am on "Course 001" course homepage
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student4 | C001   | student |
    And I add "Cohort members to group" enrolment method with:
      | Cohort       | Odd numbered users  |
      | Add to group | Groupcourse 2       |
    And I am on "Course 001" course homepage
    And I navigate to "Users > Enrolled users" in current page administration
    And I should not see "Groupcourse 2" in the "Studie Four" "table_row"
    When I add "Studie Four (student4@example.com)" user to "Odd" cohort members
    And I am on "Course 001" course homepage
    And I navigate to "Users > Enrolled users" in current page administration
    Then I should see "Groupcourse 2" in the "Studie Four" "table_row"
