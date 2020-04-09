<?php

namespace Drupal\corpus_importer;

use Drupal\Component\Utility\Html;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use writecrow\TagConverter\TagConverter;

/**
 * Class ImporterService.
 *
 * @package Drupal\corpus_importer
 */
class ImporterService {

  /**
   * Main method: execute parsing and saving of redirects.
   *
   * @param mixed $files
   *   Simple array of filepaths.
   * @param string $options
   *   User-supplied default flags.
   */
  public static function import($files, $options = []) {

    if (PHP_SAPI == 'cli' && function_exists('drush_main')) {
      ini_set("memory_limit", "4096M");
      array_slice(scandir($files), 2);
      $absolute_paths = [];
      $repository_candidates = [];
      $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($files));
      foreach ($objects as $filepath => $object) {
        if (stripos($filepath, '.txt') !== FALSE) {
          $absolute_paths[]['tmppath'] = $filepath;
        }
        if (stripos($filepath, '.txt') === FALSE) {
          $path_parts = pathinfo($filepath);
          // Get a filelist of repository materials eligible for upload.
          $repository_candidates[$path_parts['filename']] = $filepath;
        }
      }
      $texts = self::convert($absolute_paths);
      $skipped = [];
      $created = [];
      foreach ($texts as $text) {
        if ($text['type'] == 'corpus') {
          $text = ImporterHelper::validateCorpusText($text);
          $result = self::saveCorpusNode($text, $options);
        }
        if ($text['type'] == 'repository') {
          $result = self::saveRepositoryNode($text, $repository_candidates, $options);
        }
        if ($result['status'] === FALSE) {
          $skipped[] = $result['id'];
        }
        elseif ($result['status'] === TRUE) {
          $created[] = $result['id'];
          echo $result['id'] . PHP_EOL;
        }

        if (!empty($result['messages'])) {
          $messages[] = [$result['id'] => $result['messages']];
        }
      }
      echo PHP_EOL;
      echo '*** Notifications ***' . PHP_EOL;
      echo 'Created ' . count($created) . ' texts.' . PHP_EOL;
      if (count($skipped) > 0) {
        echo 'Skipped ' . count($skipped) . ' texts. ' . PHP_EOL;
        print_r($skipped);
      }
      $prepared_messages = self::prepareMessages($messages);
      print_r($prepared_messages);
      echo PHP_EOL;
    }
    else {
      // The UI-based importer. This is outdated currently.
      // Convert files into machine-readable array.
      $texts = self::convert($files);
      drupal_set_message(count($files) . ' files found.');

      // Perform validation logic on each row.
      $texts = array_filter($texts, ['self', 'preSave']);

      // Save valid texts.
      foreach ($texts as $text) {
        $operations[] = [
          ['\Drupal\corpus_importer\ImporterService', 'save'],
          [$text, $options],
        ];
      }

      $batch = [
        'title' => t('Saving Texts'),
        'operations' => $operations,
        'finished' => ['\Drupal\corpus_importer\ImporterService', 'finish'],
        'file' => drupal_get_path('module', 'corpus_importer') . '/corpus_importer.module',
      ];

      batch_set($batch);
    }
  }

  /**
   * Organize message notifications by message type.
   *
   * @param mixed $messages
   *   Instance array, containing keyed array by ID, with an array of messages.
   *
   * @return mixed[]
   *   Array keyed by message type.
   */
  protected static function prepareMessages($messages) {
    $prepared_messages = [];
    if (empty($messages)) {
      return 'No messages present.';
    }
    foreach ($messages as $instance) {
      foreach ($instance as $id => $types) {
        foreach ($types as $type) {
          if (!in_array($id, $prepared_messages[$type])) {
            $prepared_messages[$type][] = $id;
          }
        }
      }
    }
    // Provide result counts.
    foreach ($prepared_messages as $type => $items) {
      $prepared_messages['summary'][$type] = count($items);
    }
    return $prepared_messages;
  }

  /**
   * Convert tagged file into readable PHP array.
   *
   * @param mixed $files
   *   Simple array of filepaths.
   *
   * @return mixed[]
   *   Converted texts, in array format.
   */
  protected static function convert($files) {
    $data = [];
    foreach ($files as $uploaded_file) {
      $file = file_get_contents($uploaded_file['tmppath']);
      $text = TagConverter::php($file);
      $text['filename'] = basename($uploaded_file['tmppath'], '.txt');
      if (isset($text['Student ID'])) {
        $text['type'] = 'corpus';
        $data[] = $text;
      }
      elseif (isset($text['File ID'])) {
        $text['type'] = 'repository';
        $text['full_path'] = $uploaded_file['tmppath'];
        $data[] = $text;
      }
      elseif (isset($text['ID'])) {
        // Assume that files with "ID" are corpus files.
        $text['Student ID'] = $text['ID'];
        $text['type'] = 'corpus';
        $data[] = $text;
      }
    }

    return $data;
  }

  /**
   * Check for problematic data and remove or clean up.
   *
   * @param str[] $text
   *   Keyed array of texts.
   *
   * @return bool
   *   A TRUE/FALSE value to be used by array_filter.
   */
  public static function preSave(array $text) {
    return TRUE;
  }

  /**
   * Save an individual entity.
   *
   * @param str[] $text
   *   Keyed array of redirects, in the format
   *    [source, redirect, status_code, language].
   * @param str[] $options
   *   A 1 indicates that existing entities should be updated.
   * @param str[] $context
   *   Operational context for batch processes.
   */
  public static function save(array $text, array $options, array &$context) {
    if (isset($text['Student ID'])) {
      $result = self::saveCorpusNode($text, $options);
    }
    if (isset($text['File ID'])) {
      $result = self::saveRepositoryNode($text, $options);
    }
    $key = $result['id'];
    $context['results'][$key][] = $result;
  }

  /**
   * Helper function to save corpus data.
   */
  public static function saveCorpusNode($text, $options) {
    // $field_metadata will contain a keyed array of 'fields' and 'messages'.
    $field_metadata = ImporterHelper::retrieveTaxonomyIds($text, $options);
    $messages = $field_metadata['messages'];
    $fields = $field_metadata['fields'];
    $node = Node::create(['type' => 'text']);
    $node->set('title', $text['filename']);

    // Set each known field on the node type.
    foreach (ImporterMap::$corpusTaxonomies as $name => $machine_name) {
      if (!empty($fields[$machine_name])) {
        if (is_array($fields[$machine_name])) {
          $elements = [];
          foreach ($fields[$machine_name] as $delta => $term) {
            $elements[] = ['delta' => $delta, 'target_id' => $term];
          }
          $node->set('field_' . $machine_name, $elements);
        }
        else {
          $node->set('field_' . $machine_name, ['target_id' => $fields[$machine_name]]);
        }
      }
      else {
        if ($name !== 'Instructor') {
          // If a required metadata field is not present, report it.
          $messages[] = ($name . ' metadata not found');
        }
      }
    }

    // Massage TOEFL logic if entered as 'Proficiency Exam'.
    if (isset($text['Proficiency Exam'])) {
      if ($text['Proficiency Exam'] == 'TOEFL') {
        $text['TOEFL total'] = intval($text['Exam total']);
        $text['TOEFL writing'] = intval($text['Exam writing']);
        $text['TOEFL speaking'] = intval($text['Exam speaking']);
        $text['TOEFL reading'] = intval($text['Exam reading']);
        $text['TOEFL listening'] = intval($text['Exam listening']);
      }
    }

    $node->set('field_id', ['value' => $text['Student ID']]);
    $node->set('field_toefl_total', ['value' => $text['TOEFL total']]);
    $node->set('field_toefl_writing', ['value' => $text['TOEFL writing']]);
    $node->set('field_toefl_speaking', ['value' => $text['TOEFL speaking']]);
    $node->set('field_toefl_reading', ['value' => $text['TOEFL reading']]);
    $node->set('field_toefl_listening', ['value' => $text['TOEFL listening']]);

    $body = trim(html_entity_decode($text['text']));
    $body = str_replace("¶", "", $body);
    // Remove unnecessary <End Header> text.
    $body = str_replace('<End Header>', '', $body);
    $node->set('field_body', ['value' => $body, 'format' => 'plain_text']);

    $clean = Html::escape(strip_tags($body));
    $node->set('field_wordcount', ['value' => str_word_count($clean)]);

    // If dryrun, stop before actual save, but report messages.
    if ($options['dryrun'] === TRUE) {
      // Send back metadata on what happened.
      return [
        'id' => $text['filename'],
        'status' => TRUE,
        'messages' => $messages,
      ];
    }

    if ($node->save()) {
      $status = TRUE;
    }
    else {
      $status = FALSE;
    }
    // Send back metadata on what happened.
    return [
      'id' => $text['filename'],
      'status' => $status,
      'messages' => $messages,
    ];
  }

  /**
   * Helper function to save repository data.
   */
  public static function saveRepositoryNode($text, $repository_candidates, $options = []) {
    // Check if the original (.pdf) file is present.
    $file = self::uploadRepositoryResource($text['full_path'], $repository_candidates);
    $messages = [];
    if (!$file) {
      $messages[] = 'No corresponding repository file found: ' . $text['filename'];
      $output = [
        'id' => $text['filename'],
        'status' => FALSE,
        'messages' => $messages,
      ];
      return $output;
    }
    else {
      $path_parts = pathinfo($file->getFileUri());
      $text['File Type'] = $path_parts['extension'];
    }
    // The key *must* match what is provided in the original text file.
    $fields = [];
    foreach (ImporterMap::$repositoryTaxonomies as $name => $machine_name) {
      $save = TRUE;
      if (in_array($name, array_keys($text))) {
        // Skip N/A values.
        if (in_array($text[$name], ['NA', 'N/A'])) {
          $save = FALSE;
          continue;
        }
        if ($machine_name == 'document_type') {
          $doc_code = $text['Document Type'];
          // Fix legacy codes.
          if ($doc_code == "PF") {
            $doc_code = "AC";
          }
          $text['Document Type'] = ImporterMap::$docTypes[$doc_code];
        }
        if ($machine_name == 'assignment') {
          $assignment_code = $text['Assignment'];
          $text['Assignment'] = ImporterMap::$assignments[$assignment_code];
        }
        if (in_array($machine_name, ['topic'])) {
          if (is_string($text[$name])) {
            $text[$name] = str_replace("_", " ", $text[$name]);
            $multiples = preg_split("/\s?and\s?/", $text[$name]);
            if (isset($multiples[1])) {
              array_push($multiples, $text[$name]);
            }
            $text[$name] = $multiples;
          }
        }
        $tid = ImporterHelper::getTidByName($text[$name], $machine_name);
        if ($tid == 0) {
          ImporterHelper::createTerm($text[$name], $machine_name);
          $tid = ImporterHelper::getTidByName($text[$name], $machine_name);
        }
      }
      else {
        $save = FALSE;
      }
      if ($save) {
        $fields[$machine_name] = $tid;
      }
    }

    $node = Node::create(['type' => 'resource']);
    $node->set('title', $text['File ID']);
    $node->set('field_file', ['target_id' => $file->id()]);
    $node->set('field_filename', ['value' => $text['filename']]);
    foreach (ImporterMap::$repositoryTaxonomies as $name => $machine_name) {
      if (!empty($fields[$machine_name])) {
        $node->set('field_' . $machine_name, ['target_id' => $fields[$machine_name]]);
      }
    }

    $body = trim(html_entity_decode($text['text']));
    // Remove unnecessary <End Header> text.
    $body = str_replace('<End Header>', '', $body);
    $node->set('field_raw_text', ['value' => $body, 'format' => 'plain_text']);
    if ($node->save()) {
      $status = TRUE;
    }
    else {
      $status = FALSE;
    }
    // Send back metadata on what happened.
    $output = [
      'id' => $text['filename'],
      'status' => $status,
      'messages' => $messages,
    ];
    return $output;
  }

  /**
   * Utility: save file to backend.
   */
  public static function uploadRepositoryResource($full_path, $repository_candidates) {
    $path_parts = pathinfo($full_path);
    if (in_array($path_parts['filename'], array_keys($repository_candidates))) {
      $glob = glob($repository_candidates[$path_parts['filename']]);
    }
    else {
      $path_parts['dirname'] = str_replace('/Text/', '/Original/', $path_parts['dirname']);
      $original_wildcard = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.*';
      $glob = glob($original_wildcard);
    }
    if (!empty($glob[0])) {
      $original_file = $glob[0];
      print_r("Importing original file " . $original_file . PHP_EOL);
      $original_parts = pathinfo($original_file);
      $file = File::create([
        'uid' => 1,
        'filename' => $original_parts['basename'],
        'uri' => 'public://resources/' . $original_parts['basename'],
        'status' => 1,
      ]);
      $file->save();
      $file_content = file_get_contents($original_file);
      $directory = 'public://resources/';
      file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
      file_save_data($file_content, $directory . basename($original_file), FILE_EXISTS_REPLACE);
      return $file;
    }
    else {
      print_r('File not found! ' . $original_wildcard);
    }
    return FALSE;
  }

  /**
   * Batch API callback.
   */
  public static function finish($success, $results, $operations) {
    if (!$success) {
      $message = t('Finished, with possible errors.');
      drupal_set_message($message, 'warning');
    }
    if (isset($results['updated'])) {
      drupal_set_message(count($results['updated']) . ' texts updated.');
    }
    if (isset($results['created'])) {
      drupal_set_message(count($results['created']) . ' texts created.');
    }

  }

}
