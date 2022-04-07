<?php

namespace Drupal\corpus_search\Commands;

use Drush\Commands\DrushCommands;
use Drupal\corpus_search\CorpusWordFrequency;
use Drupal\corpus_search\CorpusLemmaFrequency;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class CorpusSearchCommands extends DrushCommands {

  /**
   * Count words
   *
   *
   * @command corpus:word-count
   * @aliases cwc,corpus-word-count
   */
  public function wordCount() {
    CorpusWordFrequency::analyze();
  }

  /**
   * Clear the frequency analysis data
   *
   *
   * @command corpus:word-wipe
   * @aliases cww,corpus-word-wipe
   */
  public function wordWipe() {
    CorpusWordFrequency::wipe();
    print_r('Word Frequency data reset. Run drush cwc to re-run.' . PHP_EOL);
  }

  /**
   * Derive lemmas from word frequency
   *
   *
   * @command corpus:lemma-count
   * @aliases clc,corpus-lemma-count
   */
  public function lemmaCount() {
    CorpusLemmaFrequency::analyze();
  }

  /**
   * Clear the frequency analysis data
   *
   *
   * @command corpus:lemma-wipe
   * @aliases clw,corpus-lemma-wipe
   */
  public function lemmaWipe() {
    CorpusLemmaFrequency::wipe();
    print_r('Lemma Frequency data reset. Run drush clc to re-run.' . PHP_EOL);
  }

  /**
   * Clear the frequency analysis data
   *
   * @param $word
   *   The word to lemmatize
   *
   * @command corpus:lemmatize
   * @aliases lem,lemmatize
   */
  public function lemmatize($word) {
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('search_api_lemma')->getPath();
    $lemma = CorpusLemmaFrequency::lemmatize($word, $module_path);
    print_r($word . " => " . $lemma . PHP_EOL);
  }

}
