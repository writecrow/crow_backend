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
    $results = [];
    ini_set("memory_limit", "4096M");
    $tid = self::getTidFromName($name, $vid);
    if ($tid === 0) {
      \Drupal::logger('corpus_frequency')->warning('Could not find term ' . $name . ' from vocabulary ' . $vid);
    }
    $nids = self::getTextsWithTid($tid, $vid);
    $frequency = [];
    $total_words = 0;
    foreach ($nids as $nid) {
      $data = self::getSingleTextFrequency($nid, $frequency, $total_words);
      $frequency = $data['frequency'];
      $total_words = $data['total_words'];
    }
    // Normalization per 1 million words.
    if ($total_words > 0) {
      $ratio = 1000000 / $total_words;
    }
    else {
      return $results;
    }
    // Remove stopwords.
    $no_stopwords = array_diff_key($frequency, array_flip(self::$stopwords));
    // Sort by count, descending.
    $slice = array_slice($no_stopwords, 0, 1000);
    arsort($slice, SORT_NUMERIC);
    foreach ($slice as $word => $count) {
      $results['frequency'][$word]['raw'] = $count;
      $results['frequency'][$word]['normed'] = $ratio * $count;
    }
    $results['name'] = $name;
    $results['category'] = $vid;
    $results['total_words'] = $total_words;
    $results['total_texts'] = count($nids);
    return $results;
  }

  /**
   * Count words in an individual entity.
   *
   * @param int $nid
   *   An individual node id.
   */
  public static function getSingleTextFrequency($nid, $frequency, $total_words) {
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n');
    $query->fields('n', ['text', 'entity_id']);
    $query->condition('n.entity_id', $nid, '=');
    $result = $query->execute()->fetchCol();
    if (!empty($result[0])) {
      $text = mb_convert_encoding($result[0], 'UTF-8', mb_list_encodings());
      $tokens = CorpusWordFrequency::tokenize(strip_tags($text));
      $total_words = count($tokens) + $total_words;
      foreach ($tokens as $word) {
        if (isset($frequency[$word])) {
          $frequency[$word]++;
        }
        else {
          $frequency[$word] = 1;
        }
      }
    }
    return [
      'frequency' => $frequency,
      'total_words' => $total_words,
    ];
  }

  public static function getTextsWithTid($tid, $vid) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_' . $vid, 'n');
    $query->fields('n', ['entity_id']);
    $query->condition('n.field_' . $vid . '_target_id', $tid, '=');
    $result = $query->execute()->fetchCol();
    return $result;
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
  public static function getTidFromName($name = NULL, $vid = NULL) {
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

  /**
   * Derived from https://gist.github.com/sebleier/554280
   */
  public static $stopwords = [
    'i',
    'me',
    'my',
    'myself',
    'we',
    'our',
    'ours',
    'ourselves',
    'you',
    'your',
    'yours',
    'yourself',
    'yourselves',
    'he',
    'him',
    'his',
    'himself',
    'she',
    'her',
    'hers',
    'herself',
    'it',
    'its',
    'itself',
    'they',
    'them',
    'their',
    'theirs',
    'themselves',
    'what',
    'which',
    'who',
    'whom',
    'this',
    'that',
    'these',
    'those',
    'am',
    'is',
    'are',
    'was',
    'were',
    'be',
    'been',
    'being',
    'have',
    'has',
    'had',
    'having',
    'do',
    'does',
    'did',
    'doing',
    'a',
    'an',
    'the',
    'and',
    'but',
    'if',
    'or',
    'because',
    'as',
    'until',
    'while',
    'of',
    'at',
    'by',
    'for',
    'with',
    'about',
    'against',
    'between',
    'into',
    'through',
    'during',
    'before',
    'after',
    'above',
    'below',
    'to',
    'from',
    'up',
    'down',
    'in',
    'out',
    'on',
    'off',
    'over',
    'under',
    'again',
    'further',
    'then',
    'once',
    'here',
    'there',
    'when',
    'where',
    'why',
    'how',
    'all',
    'any',
    'both',
    'each',
    'few',
    'more',
    'most',
    'other',
    'some',
    'such',
    'no',
    'nor',
    'not',
    'only',
    'own',
    'same',
    'so',
    'than',
    'too',
    'very',
    's',
    't',
    'can',
    'will',
    'just',
    'don',
    'should',
    'now',
  ];

}

