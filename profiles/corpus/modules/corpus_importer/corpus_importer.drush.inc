<?php

/**
 * @file
 * Drush commands for the pat_redirect_import module.
 */

use Drupal\corpus_importer\ImporterService;
use Drupal\corpus_importer\ImporterHelper;

/**
 * Implements hook_drush_commands().
 */
function corpus_importer_drush_command() {
  $items = [];

  $items['corpus-import'] = [
    'description' => "Import corpus tagged files",
    'arguments' => [
      'path' => 'Path to the folder that includes the files',
    ],
    'options' => [
      'dryrun' => 'Run the import logic without actually saving data.',
    ],
    'examples' => [
      'drush corpus-import corpus_data',
      'drush ci corpus_data --dryrun',
    ],
    'aliases' => ['ci'],
  ];
  $items['corpus-wipe'] = [
    'description' => "Delete all corpus nodes",
    'examples' => [
      'drush corpus-wipe',
    ],
    'aliases' => ['c-wipe'],
  ];
  $items['taxonomy-wipe'] = [
    'description' => 'Delete a terms from a given taxonomy',
    'examples' => [
      'drush taxonomy-wipe countries',
    ],
    'aliases' => ['t-wipe'],
  ];

  $items['repository-wipe'] = [
    'description' => "Delete all repository nodes",
    'examples' => [
      'drush repository-wipe',
    ],
    'aliases' => ['r-wipe'],
  ];

  return $items;
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_corpus_import($path) {
  $dryrun_value = drush_get_option('dryrun', FALSE);
  $dryrun = $dryrun_value ? TRUE : FALSE;
  if (!file_exists($path)) {
    drush_log("Path $path doesn't exist", 'error');
    exit;
  }
  if ($dryrun === TRUE) {
    print_r('This is a dry run...');
  }
  $start = time();
  ImporterService::import($path, ['lorem' => FALSE, 'dryrun' => $dryrun]);
  $finish = time();
  print_r('Completed in ' . ($finish - $start) . ' seconds.\n');
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_taxonomy_wipe($vid) {
  ImporterHelper::taxonomyWipe($vid);
  print_r("Deleted terms from vocabulary " . $vid);
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_corpus_wipe() {
  print_r("Finding existing nodes..." . PHP_EOL);
  ini_set("memory_limit", "4096M");
  $corpus_deleted = FALSE;
  $result = \Drupal::entityQuery('node')
    ->condition('type', 'text')
    ->execute();
  print_r(count($result) . " corpus nodes exist in the database..." . PHP_EOL);
  while ($corpus_deleted !== TRUE) {
    $result = \Drupal::entityQuery('node')
      ->condition('type', 'text')
      ->range(0, 20)
      ->execute();
    if (count($result) !== 0) {
      print_r(count($result) . " corpus nodes deleted..." . PHP_EOL);
      entity_delete_multiple('node', $result);
    }
    else {
      $corpus_deleted = TRUE;
    }
  }
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_repository_wipe() {
  print_r("Finding existing repository nodes..." . PHP_EOL);
  ini_set("memory_limit", "4096M");

  $result = \Drupal::entityQuery('node')
    ->condition('type', 'resource')
    ->range(0, 500)
    ->execute();
  print_r(count($result) . " repository nodes will be deleted...");
  entity_delete_multiple('node', $result);
}
