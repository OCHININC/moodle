@mod @mod_library
Feature: A teacher can choose whether library entries require approval
  In order to check entries before they are displayed
  As a user
  I need to enable entries requiring approval

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Given I add a "Library" to section "1" and I fill the form with:
      | Name | Test library name |
      | Description | Test library entries require approval |
      | Approved by default | No |
    And I log out

  Scenario: Approve and undo approve library entries
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    When I add a library entry with the following data:
      | Concept | Just a test concept |
      | Definition | Concept definition |
      | Keyword(s) | Black |
    And I log out
    # Test that students can not see the unapproved entry.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    Then I should see "No entries found in this section"
    And I log out
    # Approve the entry.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    And I follow "Waiting approval"
    Then I should see "(this entry is currently hidden)"
    And I follow "Approve"
    And I follow "Test library name"
    Then I should see "Concept definition"
    And I log out
    # Check that the entry can now be viewed by students.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    Then I should see "Concept definition"
    And I log out
    # Undo the approval of the previous entry.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    And I follow "Undo approval"
    And I log out
    # Check that the entry is no longer visible by students.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    Then I should see "No entries found in this section"

  @javascript
  Scenario: View pending approval library items
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    When I add a library entry with the following data:
      | Concept | Just a test concept |
      | Definition | Concept definition |
      | Keyword(s) | Black |
      | Tags       | Test  |
    And I log out
    And I log in as "teacher1"
    And I press "Customise this page"
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Tags" "link" in the "Navigation" "block"
    And I follow "Test"
    Then I should see "Library entries"
    And I should see "Just a test concept"
    And I should see "Entry not approved"