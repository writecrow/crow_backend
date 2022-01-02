<?php

namespace Drupal\Tests\corpus\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the initial page load returns expected JSON data.
 *
 * @group rules_ui
 */
class BaseLoad extends BrowserTestBase {


  /**
   * Use the 'corpus' installation profile.
   *
   * @var string
   */
  protected $profile = 'corpus';

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'corpus_theme';

   /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

 /**
   * Tests that the search results return the expected JSON payload.
   */
  public function testSearchResults() {
    $account = $this->drupalCreateUser(['administer content']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(200);

  }
}
