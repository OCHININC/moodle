@mod @mod_library
Feature: A teacher can choose whether to provide a printer-friendly library entries list
  In order to print libraries easily
  As a user
  I need to provide users a different view to print the library contents

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  @javascript
  Scenario: Printer-friendly library view enabled
    Given I add a "Library" to section "1" and I fill the form with:
      | Name | Test library name |
      | Description | Test library description |
      | Allow print view | Yes |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    When I add a library entry with the following data:
      | Concept | Just a test concept |
      | Definition | Concept definition |
    Then "Printer-friendly version" "link" should exist
    And "//*[contains(concat(' ', normalize-space(@class), ' '), ' printicon ')][contains(@href, 'print.php')]" "xpath_element" should exist
    And I follow "Printer-friendly version"
    And I should see "Just a test concept"

  @javascript
  Scenario: Printer-friendly library view disabled
    Given I add a "Library" to section "1" and I fill the form with:
      | Name | Test library name |
      | Description | Test library description |
      | Allow print view | No |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test library name"
    When I add a library entry with the following data:
      | Concept | Just a test concept |
      | Definition | Concept definition |
    Then "Printer-friendly version" "link" should not exist
    And "//*[contains(concat(' ', normalize-space(@class), ' '), ' printicon ')][contains(@href, 'print.php')]" "xpath_element" should not exist