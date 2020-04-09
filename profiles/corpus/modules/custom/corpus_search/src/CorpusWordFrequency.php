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
      print_r('Analyzing word frequency...' . PHP_EOL);
      if ($texts = self::retrieve()) {
        if (!empty($texts)) {
          $inc = 1;
          foreach ($texts as $key => $text) {
            $result = self::count($text);
            print_r($inc . PHP_EOL);
            $inc++;
          }
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
    $nids = \Drupal::entityQuery('node')->condition('type', 'text')->execute();
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
    $query = $connection->select('node__field_body', 'n');
    $query->fields('n', ['field_body_value', 'entity_id']);
    $query->condition('n.entity_id', $node_id, '=');
    $result = $query->execute()->fetchCol();
    if (!empty($result[0])) {
      $tokens = self::tokenize(strip_tags($result[0]));
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
          if (strlen($word) > 250) {
            continue;
          }
          $connection = \Drupal::database();
          $connection->merge('corpus_word_frequency')
            ->key(['word' => utf8_decode($word)])
            ->fields([
              'count' => $count,
              'texts' => 1,
              'ids' => $node_id . ":" . $count,
            ])
            ->expression('count', 'count + :inc', [':inc' => $count])
            ->expression('texts', 'texts + 1')
            ->expression('ids', "concat(ids, ',' :node_id)", [':node_id' => $node_id . ":" . $count])
            ->execute();
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
    $tokens = preg_split("/\s|[,.!?:*&;\"()\[\]_+=”]/", $string);
    $result = [];
    $strip_chars = ":,.!&\?;-\”'()^*";
    foreach ($tokens as $token) {
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
  }

}
