@ou @ou_vle @qtype @qtype_pmatch
Feature: Test the basic functionality of Test Question Link when preview combined Pattern Match question type
  In order to evaluate students responses, As a teacher I need to
  Create and preview combined (Combined) Pattern Match question type.

  Background:
    Given the qtype_combined plugin is installed
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I am on the "Course 1" "core_question > course question bank" page logged in as admin
    And I press "Create a new question ..."
    And I set the field "Combined" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Combined 001"
    And I set the field "Question text" to " What 5 + 5 ? [[1:pmatch:__10__]]. <br/>What is the IUPAC name of the molecule? [[2:pmatch:__20__]]. <br/>What is the pH of a 0.1M solution? [[3:numeric:__10__]]"
    Then I set the field "General feedback" to "The molecule is ethanoic acid which is more commonly known as acetic acid or in dilute solution as vinegar. The constituent elements are carbon (grey), hydrogen (white) and oxygen (red). A 0.1M solution has a pH of 2.88 and when a solution is combined with oil the result is a vinaigrette."
    And I press "Update the form"
    And I expand all fieldsets
    And "Help with Answer matching" "icon" should exist
    And I click on "Help with Answer matching" "icon"
    And I should see "If you have a short phase you want to match, you should enclose it in square brackets ([...])."
    And "More help" "link" should exist
    And I set the following fields to these values:
      | id_subqpmatch1defaultmark          | 50%                                     |
      | Spell checking                     | Do not check spelling of student        |
      | Answer must match                  | match_mw (ethanoic acid)                |
      | Pre-filled answer text             | ethaic aicd                             |
      | id_subqpmatch1generalfeedback      | You have the incorrect IUPAC name.      |
      | id_subqpmatch2defaultmark          | 25%                                     |
      | id_subqpmatch2applydictionarycheck | Do not check spelling of student        |
      | id_subqpmatch2answer_0             | match_m (10)                            |
      | id_subqpmatch2responsetemplate     | 5                                       |
      | id_subqpmatch2generalfeedback      | You have the incorrect IUPAC name.      |
      | id_subqpmatch2allowsubscript       | Yes                                     |
      | id_subqpmatch2allowsuperscript     | Yes                                     |
      | id_subqpmatch2modelanswer          | 10                                      |
      | id_subqnumeric3defaultmark         | 25%                                     |
      | id_subqnumeric3answer_0            | 2.88                                    |
      | Scientific notation                | No                                      |
      | id_subqnumeric3generalfeedback     | You have the incorrect value for the pH |
    And I press "id_submitbutton"
    And I should see "You must provide a possible response to this question, which would be graded 100% correct."
    And I set the following fields to these values:
      | id_subqpmatch1modelanswer | ethanoic acid |
    And I press "id_submitbutton"
    Then I should see "Combined 001"
    When I am on the "Combined 001" "core_question > preview" page logged in as teacher1

  @javascript
  Scenario: Should see the test question link on preview page Combined Pattern Match question type.
    # Check teacher click on the reset button.
    Given I set the field "Answer 1" to "aicd"
    When I click on "Reset" "button"
    And the field "Answer 1" matches value "ethaic aicd"
    And "Test sub question 1" "link" should be visible
    And "Test sub question 2" "link" should be visible
    When I click on "Test sub question 1" "link"
    Then I should see "Pattern-match question testing tool: Testing question: 1"
    And I should see "Showing the responses for the selected question: 1"
    When I click on "Add new response" "button"
    And I set the field "new-response" to "New test response"
    And I click on "Save" "button"
    Then I should see "New test response"
    # Check Delete response.
    And I set the field with xpath "//form[@id='attemptsform']//table[@id='responses']//td[@id='qtype-pmatch-testquestion_r50_c0']//input" to "1"
    And I click on "Delete" "button"
    And I press "Yes"
    And I press "Continue"
    Then I should not see "New test response"

  @javascript
  Scenario: Spell checking is disable
    Given "//input[@value='ethaic aicd' and @spellcheck='false']" "xpath" should be visible
    And "//textarea[@spellcheck='false']" "xpath" should exist
    When I set the field "Answer 3" to "2.55"
    And I press "Save"
    Then "//input[@value='ethaic aicd' and @spellcheck='false']" "xpath" should be visible
    And "//textarea[@spellcheck='false']" "xpath" should exist

  @javascript
  Scenario: Spell checking disable when use sup-sub on combined pmatch.
    Given I am on the "Combined 001" "core_question > edit" page logged in as teacher1
    And I expand all fieldsets
    When I set the field "Allow use of subscript" to "Yes"
    Then the "Spell checking" "field" should be disabled
    And the "Add these words to dictionary" "field" should be disabled
    And I should see "Allowing use of sub- or superscript will disable spellchecking."

  @javascript
  Scenario: Edit combine pmatch question and check the placeholder.
    Given I am on the "Combined 001" "core_question > edit" page logged in as teacher1
    When I expand all fieldsets
    And I should see "Appropriate input size:"
    And the following fields match these values:
      | subq:pmatch:1:placeholder | __15__ |
    And I set the following fields to these values:
      | Question name             | Edited question name |
      | id_subqpmatch1modelanswer |                      |
    Then the following fields match these values:
      | subq:pmatch:1:placeholder | __6__ |
    And I set the following fields to these values:
      | id_subqpmatch1modelanswer | testing one two three four |
    And the following fields match these values:
      | subq:pmatch:1:placeholder | __28__ |
