<?php

/**
 * @file
 * Drush commands for the word_frequency module.
 */

use Drupal\word_frequency\FrequencyService;

/**
 * Implements hook_drush_commands().
 */
function word_frequency_drush_command() {
  $items = [];
  $items['frequency-count'] = [
    'description' => "Count words",
    'aliases' => array('f-count'),
  ];
  $items['frequency-common'] = [
    'description' => "Return most frequent words",
    'aliases' => array('f-common'),
    'arguments' => array(
      'limit' => 'Number of results to return',
    ),
  ];
  $items['frequency-wipe'] = [
    'description' => "Clear the frequency analysis data",
    'aliases' => array('f-wipe'),
  ];
  return $items;
}

/**
 * Implements the drush command.
 */
function drush_word_frequency_frequency_count() {
  FrequencyService::analyze();
}

/**
 * Implements the drush command.
 */
function drush_word_frequency_frequency_wipe() {
  FrequencyService::wipe();
  print_r('Frequency data reset. Run drush f-count to re-run.' . PHP_EOL);
}

/**
 * Implements the drush command.
 */
function drush_word_frequency_frequency_common($limit = 100) {
  print_r(FrequencyService::mostCommon($limit));
}
