Feature: Sample feature

  Scenario: Visit the page
    Given I am on the homepage
    When I follow "Format"
    Then I should see "Formatted"
