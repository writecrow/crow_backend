<?php

/**
 * @file
 * Drush commands for the pat_redirect_import module.
 */

use Drupal\corpus_importer\ImporterService;
use Drupal\corpus_importer\ImporterHelper;
use Drupal\corpus_importer\DeDupeHelper;

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
  $items['corpus-dedupe'] = [
    'description' => "Find duplicate corpus texts",
    'examples' => [
      'drush corpus-dedupe',
    ],
    'options' => [
      'delete' => 'Delete duplicates',
    ],
    'aliases' => ['c-dedupe'],
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
    'options' => [
      'institution' => 'Limit the wipe by institution vocabulary ID',
      'filename' => 'Limit the wipe by corpus text filename',
    ],
    'aliases' => ['r-wipe'],
  ];
  $items['corpus-wipe'] = [
    'description' => "Delete all corpus nodes",
    'examples' => [
      'drush corpus-wipe',
    ],
    'options' => [
      'institution' => 'Limit the wipe by institution vocabulary ID',
      'filename' => 'Limit the wipe by corpus text filename',
    ],
    'aliases' => ['c-wipe'],
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
 * Corpus-dedupe.
 */
function drush_corpus_importer_corpus_dedupe() {
  $delete_value = drush_get_option('delete', FALSE);
  $duplicate_corpus_nodes = DeDupeHelper::audit();
  print_r($duplicate_corpus_nodes);
  $delete = $delete_value ? TRUE : FALSE;
  if ($delete) {
    foreach ($duplicate_corpus_nodes['all_matches'] as $file) {
      if (isset($file[1])) {
        $node = \Drupal::entityManager()->getStorage('node')->load($file[1]);
        $node->delete();
        print_r('Deleted node ' . $file[1]);
      }
    }
  }
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_corpus_wipe() {
  $institution_id = drush_get_option('institution', NULL);
  $filename = drush_get_option('filename', NULL);
  ini_set("memory_limit", "4096M");
  $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
  $query->condition('type', 'text');
  if ($institution_id !== NULL) {
    $query->condition('field_institution.target_id', (int) $institution_id, '=');
  }
  if ($filename !== NULL) {
    $query->condition('title', $filename);
  }
  $items = array_values($query->execute());
  if (count($items) != 0) {
    print_r(count($items) . " matching corpus nodes exist..." . PHP_EOL);
    print_r('This may take a few moments...' . PHP_EOL);
    entity_delete_multiple('node', $items);
    print_r('Deleted!' . PHP_EOL);
  }
  else {
    print_r('No corpus texts match the criteria' . PHP_EOL);
  }
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_repository_wipe() {
  $institution_id = drush_get_option('institution', NULL);
  $filename = drush_get_option('filename', NULL);
  print_r("Finding existing repository nodes..." . PHP_EOL);
  ini_set("memory_limit", "4096M");

  $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
  $query->condition('type', 'resource');
  if ($institution_id !== NULL) {
    $query->condition('field_institution.target_id', (int) $institution_id, '=');
  }
  if ($filename !== NULL) {
    $query->condition('field_filename.value', $filename);
  }
  $items = array_values($query->execute());
  if (count($items) != 0) {
    print_r(count($items) . " matching repository nodes exist..." . PHP_EOL);
    print_r('This may take a few moments...' . PHP_EOL);
    entity_delete_multiple('node', $items);
    print_r('Deleted!' . PHP_EOL);
  }
  else {
    print_r('No repository texts match the criteria' . PHP_EOL);
  }
}
