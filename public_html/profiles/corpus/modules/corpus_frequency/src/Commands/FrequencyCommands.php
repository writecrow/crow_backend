<?php

namespace Drupal\corpus_frequency\Commands;

use Drush\Commands\DrushCommands;
use Drupal\corpus_frequency\FrequencyHelper;
use Drupal\corpus_search\TextMetadata;

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
   * Generate a cached version of the frequency data.
   *
   * @command corpus:frequency
   * @aliases c-freq
   */
  public function regenerateFrequency() {
    $vocabulary = 'assignment';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary);
    foreach ($terms as $term) {
      \Drupal::logger('corpus_frequency')->notice("Rebuilt frequency data for $term->name");
      FrequencyHelper::analyze($term->name, $vocabulary);
    }
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
