<?php

namespace Drupal\corpus_importer\Commands;

use Drush\Commands\DrushCommands;
use Drupal\corpus_importer\ImporterService;
use Drupal\corpus_importer\ImporterHelper;
use Drupal\corpus_importer\DeDupeHelper;

/**
 * A Drush commandfile.
 */
class CorpusImporterCommands extends DrushCommands {

  /**
   * Import corpus tagged files.
   *
   * @param string $path
   *   Path to the folder that includes the files.
   * @param array $options
   *   An associative array of options.
   * @option dryrun
   *   Run the import logic without actually saving data.
   * @usage 0
   *   drush corpus-import corpus_data
   * @usage 1
   *   drush ci corpus_data --dryrun
   *
   * @command corpus:import
   * @aliases ci,corpus-import
   */
  public function import($path, array $options = ['dryrun' => NULL]) {
    $dryrun_value = $this->getOption($options, 'dryryn');
    $dryrun = $dryrun_value ? TRUE : FALSE;
    if (!file_exists($path)) {
      $this->logger()->warning("Path $path doesn't exist", 'error');
      exit;
    }
    if ($dryrun === TRUE) {
      $this->output()->writeln('This is a dry run...');
    }
    $start = time();
    ImporterService::import($path, ['lorem' => FALSE, 'dryrun' => $dryrun]);
    $finish = time();
    $this->output()->writeln('Completed in ' . ($finish - $start) . ' seconds.\n');
  }

  /**
   * Find duplicate corpus texts.
   *
   * @param array $options
   *   An associative array of options.
   * @option delete
   *   Delete duplicates
   * @usage 0
   *   drush corpus-dedupe
   *
   * @command corpus:dedupe
   * @aliases c-dedupe,corpus-dedupe
   */
  public function dedupe(array $options = ['delete' => NULL]) {
    $delete_value = $this->getOption($options, 'delete');
    $duplicate_corpus_nodes = DeDupeHelper::audit();
    print_r($duplicate_corpus_nodes);
    $delete = $delete_value ? TRUE : FALSE;
    if ($delete) {
      foreach ($duplicate_corpus_nodes['all_matches'] as $file) {
        if (isset($file[1])) {
          $node = \Drupal::entityTypeManager()->getStorage('node')->load($file[1]);
          $node->delete();
          $this->output()->writeln('Deleted node ' . $file[1]);
        }
      }
    }
  }

  /**
   * Delete all terms from the stated taxonomy vocabulary.
   *
   * @usage 0
   *   drush taxonomy-wipe country
   *
   * @command taxonomy:wipe
   * @aliases t-wipe,taxonomy-wipe
   */
  public function taxonomyWipe($vid) {
    ImporterHelper::taxonomyWipe($vid);
    $this->output()->writeln("Deleted terms from vocabulary " . $vid);
  }

  /**
   * Delete all repository nodes.
   *
   * @param array $options
   *   An associative array of options.
   * @option institution
   *   Limit the wipe by institution vocabulary TID
   * @option filename
   *   Limit the wipe by corpus text filename
   * @usage 0
   *   drush repository-wipe --institution="7"
   *
   * @command repository:wipe
   * @aliases r-wipe,repository-wipe
   */
  public function repositoryWipe(array $options = ['institution' => NULL, 'filename' => NULL]) {
    $institution_id = $this->getOption($options, 'institution');
    $filename = $this->getOption($options, 'filename');
    $this->output()->writeln("Finding existing repository nodes...");
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
      $this->output()->writeln(count($items) . " matching repository nodes exist...");
      $this->output()->writeln('This may take a few moments...');
      $storage_handler = \Drupal::entityTypeManager()
        ->getStorage('node');
      $entities = $storage_handler
        ->loadMultiple($items);
      $storage_handler
        ->delete($entities);
      $this->output()->writeln('Deleted!');
    }
    else {
      $this->output()->writeln('No repository texts match the criteria');
    }
  }

  /**
   * Delete all corpus nodes.
   *
   * @param array $options
   *   An associative array of options.
   * @option institution
   *   Limit the wipe by institution vocabulary TID
   * @option filename
   *   Limit the wipe by corpus text filename
   * @usage 0
   *   drush corpus-wipe --institution="7"
   *
   * @command corpus:wipe
   * @aliases c-wipe,corpus-wipe
   */
  public function corpusWipe(array $options = ['institution' => NULL, 'filename' => NULL]) {
    $institution_id = $this->getOption($options, 'institution');
    $filename = $this->getOption($options, 'filename');
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
      $this->output()->writeln(count($items) . " matching corpus nodes exist...");
      $this->output()->writeln('This may take a few moments...');
      $storage_handler = \Drupal::entityTypeManager()
        ->getStorage('node');
      $entities = $storage_handler
        ->loadMultiple($items);
      $storage_handler
        ->delete($entities);
      $this->output()->writeln('Deleted!');
      $this->output()->writeln('Deleted!');
    }
    else {
      $this->output()->writeln('No corpus texts match the criteria');
    }
  }

  /**
   * Get the value of an option.
   *
   * @param array $options
   *   The options array.
   * @param string $name
   *   The option name.
   * @param mixed $default
   *   The default value of the option.
   *
   * @return mixed|null
   *   The option value, defaulting to NULL.
   */
  protected function getOption(array $options, $name, $default = NULL) {
    return isset($options[$name])
      ? $options[$name]
      : $default;
  }

}
