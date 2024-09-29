<?php

namespace Drupal\corpus_frequency;

use Drupal\corpus_search\TextMetadataConfig;
use Drupal\corpus_search\CorpusWordFrequency;

/**
 * Class CorpusWordFrequency.
 *
 * @package Drupal\corpus_frequency
 */
class FrequencyHelper {

  /**
   * Count words by category.
   */
  public static function analyze($name, $vid) {
    ini_set("memory_limit", "4096M");
    $tid = self::getTidByName($name, $vid);
    if ($tid === 0) {
      print_r('Could not find term ' . $name . ' from vocabulary ' . $vid);
    }
    print_r('Generating frequency for ' . $name . ' (tid ' . $tid . ')...' . PHP_EOL);
    $nodes = self::getTextsWithTid($tid, $vid);
    $frequency = [];
    foreach (array_keys($nodes) as $nid) {
      print_r('Node' . $nid . PHP_EOL);
      // $frequency = self::count($nid, $frequency);
    }
    arsort($frequency, SORT_NUMERIC);
    // print_r($frequency);
    // if ($texts = self::retrieve()) {
    //   if (!empty($texts)) {
    //     $inc = 1;
    //     foreach ($texts as $key => $text) {
    //       $result = self::count($text);
    //       print_r($inc . PHP_EOL);
    //       $inc++;
    //     }
    //   }
    // }
  }

  /**
   * Count words in an individual entity.
   *
   * @param int $node_id
   *   An individual node id.
   */
  public static function count($node_id, $frequency) {
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n');
    $query->fields('n', ['text', 'entity_id']);
    $query->condition('n.entity_id', $node_id, '=');
    $result = $query->execute()->fetchCol();
    if (!empty($result[0])) {
      $text = mb_convert_encoding($result[0], 'UTF-8', mb_list_encodings());
      $tokens = CorpusWordFrequency::tokenize(strip_tags($text));
      foreach ($tokens as $word) {
        if (isset($frequency[$word])) {
          $frequency[$word]++;
        }
        else {
          $frequency[$word] = 1;
        }
      }
      // if (!empty($frequency)) {
      //   foreach ($frequency as $word => $count) {
      //     if (mb_strlen($word) > 25) {
      //       continue;
      //     }
      //     $connection->merge('corpus_word_frequency')
      //     ->key(['word' => utf8_decode($word)])
      //       ->fields([
      //         'count' => $count,
      //         'texts' => 1,
      //         'ids' => $node_id . ":" . $count,
      //       ])
      //       ->expression('count', 'count + :inc', [':inc' => $count])
      //       ->expression('texts', 'texts + 1')
      //       ->expression('ids', "concat(ids, ',' :node_id)", [':node_id' => $node_id . ":" . $count])
      //       ->execute();
      //   }
      // }
    }
    return $frequency;
  }

  public static function getTextsWithTid($tid, $vid) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => TextMetadataConfig::$corpusSourceBundle,
        'field_' . $vid => $tid,
      ]);
    return $nodes;
  }

  /**
   * Utility: find term by name and vid.
   *
   * @param string $name
   *   Term name.
   * @param string $vid
   *   Term vid.
   *
   * @return int
   *   Term id or 0 if none.
   */
  public static function getTidByName($name = NULL, $vid = NULL) {
    if (empty($name) || empty($vid)) {
      return 0;
    }
    $properties = [
      'name' => $name,
      'vid' => $vid,
    ];
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);
    return !empty($term) ? $term->id() : 0;
  }

}

