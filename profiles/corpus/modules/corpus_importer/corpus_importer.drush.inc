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
  $items['corpus-wipe'] = [
    'description' => "Delete all corpus nodes",
    'examples' => [
      'drush corpus-wipe',
    ],
    'options' => [
      'institution' => 'Limit the wipe by institution vocabulary ID',
    ],
    'aliases' => ['c-wipe'],
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
  ini_set("memory_limit", "4096M");
  $corpus_deleted = FALSE;
  $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
  $query->condition('type', 'text');
  if ($institution_id !== NULL) {
    $query->condition('field_institution.target_id', (int) $institution_id, '=');
  }
  $items = array_values($query->execute());
  print_r(count($items) . " matching corpus nodes exist..." . PHP_EOL);
  entity_delete_multiple('node', $items);
  print_r('Deleted!' . PHP_EOL);
}

/**
 * Implements the drush command.
 */
function drush_corpus_importer_repository_wipe() {
  print_r("Finding existing repository nodes..." . PHP_EOL);
  ini_set("memory_limit", "4096M");

  $result = \Drupal::entityQuery('node')
    ->condition('type', 'resource')
    ->execute();
  print_r(count($result) . " repository nodes will be deleted...");
  entity_delete_multiple('node', $result);
}
