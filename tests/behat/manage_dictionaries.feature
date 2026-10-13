@local @local_dictionaries @javascript
Feature: Manage reference dictionaries
  In order to resolve stored codes into labels
  As an admin
  I need to create reference domains and import code-label pairs

  Scenario: Create a reference domain open to logged-in users
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > Manage reference dictionaries" in site administration
    And I expand all fieldsets
    And I set the field "Domain shortname" in the "Create or update domain" "fieldset" to "iso639"
    And I set the field "Domain name" in the "Create or update domain" "fieldset" to "Languages"
    And I set the field "Who can look up items" in the "Create or update domain" "fieldset" to "Any logged-in user except guests"
    And I press "Save domain"
    Then I should see "Domain saved: iso639"
    And "Languages" "text" should exist in the "iso639" "table_row"
    And "Any logged-in user except guests" "text" should exist in the "iso639" "table_row"

  Scenario: Reject an invalid domain shortname
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > Manage reference dictionaries" in site administration
    And I expand all fieldsets
    And I set the field "Domain shortname" in the "Create or update domain" "fieldset" to "Bad Domain!"
    And I press "Save domain"
    Then I should see "Invalid domain shortname. Use lowercase letters, numbers, and underscore only."

  Scenario: Import reference items from pasted CSV and list rejected rows
    Given I log in as "admin"
    And I navigate to "Plugins > Local plugins > Manage reference dictionaries" in site administration
    And I expand all fieldsets
    And I set the field "Domain shortname" in the "Create or update domain" "fieldset" to "iso639"
    And I press "Save domain"
    When I expand all fieldsets
    And I set the field "Domain shortname" in the "Import CSV" "fieldset" to "iso639"
    And I set the field "CSV content (optional)" to multiline:
      """
      code;label
      pt;Portuguese
      en;English
      only-one-column
      """
    And I press "Import items"
    Then I should see "Import completed. Inserted: 2, Updated: 0, Ignored: 0, Duplicates: 0, Rejected: 1."
    And I should see "Line 4: the row has fewer columns than code and label need."
