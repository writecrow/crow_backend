<?php

namespace Drupal\corpus_frequency\Commands;

use Drush\Commands\DrushCommands;
use Drupal\corpus_frequency\FrequencyHelper;

/**
 * A Drush commandfile.
 */
class FrequencyCommands extends DrushCommands {

  /**
   * Count words
   *
   *
   * @command corpus:frequency-count
   * @aliases cfc
   */
  public function frequencyCount() {
    $name = 'Analytical Memo';
    $vocabulary = 'assignment';
    $data = FrequencyHelper::analyze($name, $vocabulary);
    print_r($data);
  }

  /**
   * Clear the frequency analysis data
   *
   *
   * @command corpus:frequency-wipe
   * @aliases cfw
   */
  public function frequencyWipe() {
    FrequencyHelper::wipe();
  }
}
