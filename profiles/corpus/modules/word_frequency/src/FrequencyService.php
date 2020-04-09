<?php

namespace Drupal\word_frequency;

use Drupal\node\Entity\Node;

/**
 * Class ImporterService.
 *
 * @package Drupal\corpus_importer
 */
class FrequencyService {

  public static function mostCommon($range = 100) {
    // Create an object of type Select and directly
    // add extra detail to this query object: a condition, fields and a range.
    $connection = \Drupal::database();
    $query = $connection->select('word_frequency', 'f')
      ->fields('f', ['word', 'count'])
      ->orderBy('count', 'DESC')
      ->range(0, $range);
    $result = $query->execute();
    return $result->fetchAllKeyed();
  }

  public static function totalWords() {
    $connection = \Drupal::database();
    $query = $connection->select('word_frequency', 'f');
    $query->addExpression('sum(count)', 'total');
    $result = $query->execute()->fetchAssoc();
    $value = $result['total'];
    return $value;
  }

  public static function simpleSearch($word, $case = 'insensitive') {
    // Create an object of type Select and directly
    // add extra detail to this query object: a condition, fields and a range.
    $connection = \Drupal::database();
    $query = $connection->select('word_frequency', 'f')->fields('f', ['count', 'ids']);
    $query->condition('word', db_like($word), 'LIKE BINARY');
    $result = $query->execute();
    $counts = $result->fetchAssoc();
    if (!$counts['count']) {
      $counts['count'] = 0;
    }
    $counts['raw'] = $counts['count'];
    if ($case == 'insensitive') {
      $query = $connection->select('word_frequency', 'f')->fields('f', ['count', 'ids']);
      if (ctype_lower($word)) {
        $query->condition('word', db_like(ucfirst($word)), 'LIKE BINARY');
      }
      else {
        $query->condition('word', db_like(strtolower($word)), 'LIKE BINARY');
      }
      $result = $query->execute();
      $item = $result->fetchAssoc();
      $counts['raw'] = $counts['raw'] + $item['count'];
      if ($item['count']) {
        $counts['ids'] = $counts['ids'] . ',' . $item['ids'];
      } 
    }
    if (!$counts['ids']) {
      $ids = [];
    }
    else {
      $ids = explode(',', $counts['ids']);
    }
    unset($counts['count']);
    $counts['texts'] = count(array_unique($ids));
    $counts['ids'] = $ids;
    return $counts;
  }

  public static function countTextsContaining($words) {
    // Note: this method was avoided, for performance & consistency reasons.
    // Nevertheless, it's a useful example of looped query conditions.
    // Create an object of type Select and directly
    // add extra detail to this query object: a condition, fields and a range.
    // $connection = \Drupal::database();
    // $query = $connection->select('node__field_body', 'f')->fields('f', ['field_body_value']);
    // $query->condition('bundle', 'text', '=');
    // // Improve query matching by guessing likely start & end values.
    // $and_condition_1 = $query->orConditionGroup();
    // foreach ($words as $word => $type) {
    //   if ($type == 'quoted') {
    //     $and_condition_1->condition('field_body_value', "%" . $connection->escapeLike(' ' . $word . ' ') . "%", 'LIKE BINARY');
    //   }
    //   else {
    //     $and_condition_1->condition('field_body_value', "%" . $connection->escapeLike(' ' . $word . ' ') . "%", 'LIKE');
    //   }
    // }
    // $texts_count = $query->condition($and_condition_1)->countQuery()->execute()->fetchField();
    $connection = \Drupal::database();
    $all_ids = [];
    foreach ($words as $word => $type) {
      $ids = [];
      $query = $connection->select('word_frequency', 'f')->fields('f', ['ids']);
      $query->condition('word', db_like($word), 'LIKE BINARY');
      $result = $query->execute();
      $id_string = $result->fetchField();
      if (!empty($id_string)) {
        $ids = explode(',', $id_string);
      }
      // Append case-insensitive results.
      if ($type == 'standard') {
        $query = $connection->select('word_frequency', 'f')->fields('f', ['ids']);
        if (ctype_lower($word)) {
          $query->condition('word', db_like(ucfirst($word)), 'LIKE BINARY');
        }
        else {
          $query->condition('word', db_like(strtolower($word)), 'LIKE BINARY');
        }
        $result = $query->execute();
        $id_string = $result->fetchField();
        if (!empty($id_string)) {
          $insensitive_ids = explode(',', $id_string);
        }
        $ids = array_unique(array_merge($insensitive_ids, $ids));
      }
      if (!empty($ids)) {
        $all_ids = array_unique(array_merge($ids, $all_ids));
      }
    }
    return count($all_ids);
  }

  public static function phraseSearch($phrase) {
    $terms = self::getTermMapping();
    // Create an object of type Select and directly
    // add extra detail to this query object: a condition, fields and a range.
    $connection = \Drupal::database();
    $query = $connection->select('node__field_body', 'f');
    $query->leftJoin('node_field_data', 'n', 'f.entity_id = n.nid');
    $query->leftJoin('node__field_course', 'c', 'f.entity_id = c.entity_id');
    $query->fields('n', ['title', 'type']);
    $query->fields('f', ['entity_id', 'field_body_value']);
    $query->fields('c', ['field_course_target_id']);
    $query->condition('n.type', 'text', '=');

    // Apply facet conditions.
    // $query->condition('c.field_course_target_id', '4', '=');

    // Apply other field conditions (TOEFL, ID, etc.).

    // Apply text conditions.
    $and_condition_1 = $query->orConditionGroup()
      ->condition('field_body_value', "%" . $connection->escapeLike($phrase) . "%", 'LIKE BINARY');
    $result = $query->condition($and_condition_1)->execute();

    $raw_texts = $result->fetchAll();
    $count = 0;
    $normed = 0;
    $texts = 0;
    $ids = [];
    $excerpts = [];
    if (!empty($raw_texts)) {
      $inc = 0;
      $total = FrequencyService::totalWords();
      $ratio = 10000 / $total;
      foreach ($raw_texts as $result) {
        $body = $result->field_body_value;
        if ($inc < 20) {
          $excerpts[$result->title]['excerpt'] = self::getExcerpt($body, $phrase);
          $excerpts[$result->title]['course'] = $terms[$result->field_course_target_id];
        }
        $count = $count + substr_count($body, $phrase);
        $ids[] = $result->entity_id;
        $inc++;
      }
      $texts = count($raw_texts);
    }
    return ['raw' => $count, 'texts' => $texts, 'ids' => $ids, 'excerpts' => $excerpts];
  }

  public static function getTermMapping() {
    $connection = \Drupal::database();
    $query = $connection->select('taxonomy_term_field_data', 't');
    $query->fields('t', ['tid', 'name']);
    $result = $query->execute();
    return $result->fetchAllKeyed();
  }

  public static function getExcerpt($text, $token) {
    $pos = strpos($text, $token);
    $start = $pos - 100 < 0 ? 0 : $pos - 100;
    $excerpt = substr($text, $start, 250);
    // Boldface match.
    return str_replace($token, '<mark>' . $token . '</mark>', $excerpt);
  }

  /**
   * Main method: retrieve all texts & count words sequentially.
   */
  public static function analyze() {
    if (PHP_SAPI == 'cli' && function_exists('drush_main')) {
      ini_set("memory_limit", "4096M");
      print_r('Analyzing word frequency...' . PHP_EOL);
      if ($texts = self::retrieve()) {
        if (!empty($texts)) {
          foreach ($texts as $key => $text) {
            $result = self::count($text);
            print_r($result . PHP_EOL);
          }
        }
      }
    }
    else {
      // Convert files into machine-readable array.
      $texts = self::retrieve();
      drupal_set_message(count($texts) . ' texts analyzed.');

      // Save valid texts.
      foreach ($texts as $text) {
        $operations[] = [
          ['\Drupal\word_frequency\FrequencyService', 'processor'],
          [$text],
        ];
      }

      $batch = [
        'title' => t('Calculating Frequency'),
        'operations' => $operations,
        'finished' => ['\Drupal\word_frequency\FrequencyService', 'finish'],
        'file' => drupal_get_path('module', 'word_frequency') . '/word_frequency.module',
      ];

      batch_set($batch);
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
   * Batch processor for counting.
   *
   * @param int $node_id
   *   An individual node id.
   * @param str[] $context
   *   Operational context for batch processes.
   */
  public static function processor($node_id, array &$context) {
    $result = self::count($node_id);
    if ($result) {
      $context['results'][$node_id][] = $node_id;
    }
  }

  /**
   * Count words in an individual entity.
   *
   * @param int $node_id
   *   An individual node id.
   */
  public static function count($node_id) {
    $result = FALSE;
    $node = Node::load($node_id);
    if ($body = $node->field_body->getValue()) {
      $tokens = self::tokenize($body[0]['value']);
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
          $connection->merge('word_frequency')
            ->key(['word' => utf8_decode($word)])
            ->fields([
              'count' => $count,
              'texts' => 1,
              'ids' => $node_id,
            ])
            ->expression('count', 'count + :inc', [':inc' => $count])
            ->expression('texts', 'texts + 1')
            ->expression('ids', "concat(ids, ',' :node_id)", [':node_id' => $node_id])
            ->execute();
        }
      }
      $result = $node_id;
    }
    return $result;
  }

  public static function tokenize($string) {
    $tokens = preg_split("/\s|[,.!?;\"”]/", $string);
    $result = [];
    $strip_chars = ":,.!&\?;\”'()";
    foreach ($tokens as $token) {
      $token = trim($token, $strip_chars);
      if ($token) {
        $result[] = $token;
      }
    }
    return $result;
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
      drupal_set_message(count($results['created']) . ' texts analyzed.');
    }

  }

  public static function wipe() {
    $connection = \Drupal::database();
    $query = $connection->delete('word_frequency');
    $query->execute();
  }

}
