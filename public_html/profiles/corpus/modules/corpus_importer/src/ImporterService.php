<?php

namespace Drupal\corpus_importer;

use Drupal\Core\File\FileSystemInterface;
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
      $new = 0;
      $updated = 0;
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
        if ($result['new_updated'] === 'new') {
          $new++;
        }
        if ($result['new_updated'] === 'updated') {
          $updated++;
        }
        if (!empty($result['messages'])) {
          $messages[] = [$result['id'] => $result['messages']];
        }
      }
      $output = [];
      $output[] = '*** Notifications ***';
      $output[] = 'Processed ' . count($created) . ' texts.';
      if (count($skipped) > 0) {
        $output[] = 'Skipped ' . count($skipped) . ' texts. ';
        $output[] = $skipped;
      }
      $output[] = 'New: ' . $new;
      $output[] = 'Updated: ' . $updated;
      $prepared_messages = self::prepareMessages($messages);
      $output[] = $prepared_messages;
      print_r($output);
    }
    else {
      // The UI-based importer. This is outdated currently.
      // Convert files into machine-readable array.
      $texts = self::convert($files);
      \Drupal::messenger()->addStatus(count($files) . ' files found.');

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
        'file' => \Drupal::service('extension.list.module')->getPath('corpus_importer') . '/corpus_importer.module',
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
          if (!isset($prepared_messages[$type])) {
            $prepared_messages[$type] = [];
          }
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
      if (isset($text['Student ID']) || isset($text['Student IDs'])) {
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
    $existing_node = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('title', $text['filename'])
      ->sort('nid', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!empty($existing_node)) {
      $new_updated = 'updated';
      $node = Node::load(reset($existing_node));
    }
    else {
      $node = Node::create(['type' => 'text']);
      $node->set('title', $text['filename']);
      $new_updated = 'new';
    }
    $node->setNewRevision(FALSE);

    // Set each known field on the node type.
    foreach (ImporterMap::$corpusTaxonomies as $name => $machine_name) {
      if (isset($fields[$machine_name])) {
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
    if (!isset($text['Student ID'])) {
      print_r($text);
      print_r('This text has no discernable Student ID');
      die();
    }
    // Convert student IDs to array for storage normalization.
    if (!is_array($text['Student ID'])) {
      $text['Student ID'] = [$text['Student ID']];
    }

    foreach ($text['Student ID'] as $student_id) {
      $node->field_id[] = ['value' => $student_id];
    }
    // Set authorship.
    $authorship = 'Individually-authored';
    if (count($text['Student ID']) > 1) {
      $authorship = 'Group-authored';
    }
    $term_data = ImporterHelper::getOrCreateTidFromName($authorship, 'authorship', []);
    if ($term_data) {
      $node->set('field_authorship', ['target_id' => $term_data['tid']]);
    }
    if (isset($text['TOEFL total'])) {
      $node->set('field_toefl_total', ['value' => $text['TOEFL total']]);
      $node->set('field_toefl_writing', ['value' => $text['TOEFL writing']]);
      $node->set('field_toefl_speaking', ['value' => $text['TOEFL speaking']]);
      $node->set('field_toefl_reading', ['value' => $text['TOEFL reading']]);
      $node->set('field_toefl_listening', ['value' => $text['TOEFL listening']]);
    }

    $body = trim(html_entity_decode($text['text']));
    $body = str_replace("¶", "", $body);
    $body = preg_replace('/¤/', 'a', $body);
    // Remove unnecessary <End Header> text.
    $body = str_replace('<End Header>', '', $body);

    $clean = Html::escape(strip_tags($body));
    $node->set('field_wordcount', ['value' => str_word_count($clean)]);

    if ($node->save()) {
      $status = TRUE;
      // Save the text content to an external table.
      $connection = \Drupal::database();
      $connection->merge('corpus_texts')
        ->key('filename', $text['filename'])
        ->fields([
          'filename' => $text['filename'],
          'entity_id' => $node->id(),
          'text' => $body,
        ])->execute();
    }
    else {
      $status = FALSE;
    }
    // Send back metadata on what happened.
    return [
      'id' => $text['filename'],
      'status' => $status,
      'new_updated' => $new_updated,
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
        if ($machine_name == 'mode') {
          $mode = $text['Mode'];
          if (in_array($mode, array_keys(ImporterMap::$modeFixes))) {
            $text[$name] = ImporterMap::$modeFixes[$mode];
          }
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
              $text[$name] = $multiples;
            }
          }
        }
        if (!is_array($text[$name])) {
          $text[$name] = [$text[$name]];
        }
        $tids = [];
        foreach ($text[$name] as $t) {
          $tid = ImporterHelper::getTidByName($t, $machine_name);
          if ($tid == 0) {
            ImporterHelper::createTerm($t, $machine_name);
            $tid = ImporterHelper::getTidByName($t, $machine_name);
          }
          $tids[] = $tid;
        }
      }
      else {
        $save = FALSE;
      }
      if ($save) {
        $fields[$machine_name] = $tids;
      }
    }

    $node = Node::create(['type' => 'resource']);
    $node->set('title', $text['File ID']);
    $node->set('field_file', ['target_id' => $file->id()]);
    $node->set('field_filename', ['value' => $text['filename']]);
    foreach (ImporterMap::$repositoryTaxonomies as $name => $machine_name) {
      if (isset($fields[$machine_name])) {
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
    }

    $body = trim(html_entity_decode($text['text']));
    // One more chance to enforce utf-8
    $body = mb_convert_encoding($body, 'UTF-8', mb_list_encodings());
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
      \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      \Drupal::service('file.repository')->writeData($file_content, $directory . basename($original_file), FileSystemInterface::EXISTS_REPLACE);
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
      \Drupal::messenger()->addWarning($message);
    }
    if (isset($results['updated'])) {
      \Drupal::messenger()->addStatus(count($results['updated']) . ' texts updated.');
    }
    if (isset($results['created'])) {
      \Drupal::messenger()->addStatus(count($results['created']) . ' texts created.');
    }

  }

}
