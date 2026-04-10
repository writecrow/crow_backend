<?php

namespace Drupal\corpus_search;

/**
 * Class CorpusWordFrequency.
 *
 * @package Drupal\corpus_search
 */
class CorpusWordFrequency {

  /**
   * Main method: retrieve all texts & count words sequentially.
   */
  public static function analyze() {
    if (PHP_SAPI == 'cli' && function_exists('drush_main')) {
      ini_set("memory_limit", "4096M");
      $texts = self::retrieve();
      if (empty($texts)) {
        print_r('No texts found?!' . PHP_EOL);
        return;
      }
      // Temporary local file storage before database import.
      array_map('unlink', glob("cwc/*.*"));
      rmdir('cwc');
      mkdir('cwc');
      print_r('Analyzing word frequency...' . PHP_EOL);
      if (!empty($texts)) {
        $inc = 1;
        foreach (array_values($texts) as $text) {
          self::count($text);
          print_r($inc . PHP_EOL);
          $inc++;
        }
      }
      print_r('Saving to database...');
      /** @var \Drupal\Core\Database\Connection $connection */
      $connection = \Drupal::database();
      $files = scandir('cwc');
      foreach ($files as $filename) {
        if ('.' !== $filename && '..' !== $filename && is_file("cwc/" . $filename)) {
          $word = basename($filename, '.txt');
          print_r($word . PHP_EOL);
          $contents = file("cwc/" . $filename);
          $texts = count($contents);
          $count = 0;
          $ids = [];
          foreach ($contents as $line) {
            $ids[] = trim($line);
            $parts = explode(":", $line);
            $count = $count + $parts[1];
          }
          $connection->insert('corpus_word_frequency')
            ->fields([
              'word' => $word,
              'count' => $count,
              'texts' => $texts,
              'ids' => implode(",", array_unique($ids)),
            ])
            ->execute();
        }
      }
    }
  }

  /**
   * Retrieve which entities should be counted.
   *
   * @return int[]
   *   IDs of texts
   */
  protected static function retrieve() {
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'text')->execute();
    if (!empty($nids)) {
      return(array_values($nids));
    }
    return FALSE;
  }

  /**
   * Count words in an individual entity.
   *
   * @param int $node_id
   *   An individual node id.
   */
  public static function count($node_id) {
    $result = FALSE;
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n');
    $query->fields('n', ['text', 'entity_id']);
    $query->condition('n.entity_id', $node_id, '=');
    $result = $query->execute()->fetchCol();
    if (!empty($result[0])) {
      $text = mb_convert_encoding($result[0], 'UTF-8', mb_list_encodings());
      $tokens = self::tokenize(strip_tags($text));
      foreach ($tokens as $word) {
        if (isset($frequency[$word])) {
          $frequency[$word]++;
        }
        else {
          $frequency[$word] = 1;
        }
      }
      if (!empty($frequency)) {
        foreach ($frequency as $word => $count) {
          $word = (string) $word;
          if (mb_strlen($word) > 25) {
            continue;
          }
          // Filter out strings with non-word characters.
          if (preg_match('/[^A-Za-z]/', $word)) {
            continue;
          }
          $data = "$node_id:$count" . PHP_EOL;
          file_put_contents("cwc/" . $word . ".txt", $data, FILE_APPEND);
        }
      }
      $result = $node_id;
    }
    return $result;
  }

  /**
   * Split on word boundaries.
   */
  public static function tokenize($string) {
    // Remove URLs.
    $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@";
    $string = preg_replace($regex, ' ', $string);

    // This regex is similar to any non-word character (\W),
    // but retains the following symbols: @'#$%
    $tokens = preg_split("/\s|%20|[,.!?:*\/&;\"()\[\]_+=”]/", $string);
    $result = [];
    $strip_chars = ":,.!&\?;-\”'()^*";
    foreach ($tokens as $token) {
      $token = mb_convert_encoding($token, "UTF-8", mb_detect_encoding($token));
      if (is_numeric($token)) {
        continue;
      }
      if (strlen($token) == 1) {
        if (!in_array($token, ["a", "i", "I", "A"])) {
          continue;
        }
      }
      $token = trim($token, $strip_chars);
      if ($token) {
        $result[] = $token;
      }
    }
    return $result;
  }

  /**
   * Callback function to truncate the table.
   */
  public static function wipe() {
    $connection = \Drupal::database();
    $query = $connection->delete('corpus_word_frequency');
    $query->execute();
    array_map('unlink', glob("cwc/*.*"));
    rmdir('cwc');
  }

}
