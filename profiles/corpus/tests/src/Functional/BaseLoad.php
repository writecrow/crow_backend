<?php

namespace Drupal\Tests\corpus\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\corpus_importer\ImporterService;

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
  protected $defaultTheme = 'crow_theme';

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
    $account = $this->drupalCreateUser(['access content overview']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $data = drupal_get_path('profile', 'corpus') . '/tests/test_data';
    $this->assertEquals('profiles/corpus/tests/test_data', $data);
    $this->assertEquals(TRUE, file_exists($data));
    ImporterService::import($data, ['lorem' => FALSE, 'dryrun' => FALSE]);
  }
}
