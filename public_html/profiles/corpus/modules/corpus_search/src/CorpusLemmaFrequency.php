<?php

namespace Drupal\corpus_search;

/**
 * Class CorpusLemmaFrequency.
 *
 * @package Drupal\corpus_search
 */
class CorpusLemmaFrequency {

  /**
   * Main method: retrieve all texts & count words sequentially.
   */
  public static function analyze() {
    if (PHP_SAPI == 'cli' && function_exists('drush_main')) {
      ini_set("memory_limit", "4096M");
      print_r('Analyzing lemma frequency...' . PHP_EOL);
      $module_handler = \Drupal::service('module_handler');
      $module_path = $module_handler->getModule('search_api_lemma')->getPath();
      if ($words = self::retrieve()) {
        if (!empty($words)) {
          $inc = 0;
          foreach ($words as $word => $texts) {
            if ($inc < 100000) {
              $result = self::count($word, $texts, $module_path);
              if ($result) {
                print_r($result . PHP_EOL);
              }
            }
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
   *   The entire list of words.
   */
  protected static function retrieve() {
    $connection = \Drupal::database();
    $query = $connection->select('corpus_word_frequency', 'c');
    $query->fields('c', ['word', 'ids']);
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Retrieve which entities should be counted.
   *
   * @return string[]
   *   The entire list of words.
   */
  protected static function getExisting($lemma) {
    $connection = \Drupal::database();
    $query = $connection->select('corpus_lemma_frequency', 'l');
    $query->condition('l.word', $lemma, '=');
    $query->fields('l', ['ids']);
    return $query->execute()->fetchCol();
  }

  /**
   * Count words in an individual entity.
   *
   * @param string $word
   *   An individual word.
   */
  public static function count($word, $texts, $module_path) {
    if (!ctype_alpha($word[0])) {
      return;
    }
    $word = strtolower($word);

    $lemma = self::lemmatize($word, $module_path);
    $existing = self::getExisting($lemma);
    if (!empty($existing[0])) {
      $old_text_ids = [];
      // The ids are stored in the format NID:COUNT,NID:COUNT.
      $id_pairs = explode(',', $existing[0]);
      foreach ($id_pairs as $pair) {
        $nid_and_count = explode(':', $pair);
        $old_text_ids{$nid_and_count[0]} = $nid_and_count[1];
      }
      $new_text_ids = [];
      // The ids are stored in the format NID:COUNT,NID:COUNT.
      $id_pairs = explode(',', $texts);
      foreach ($id_pairs as $pair) {
        $nid_and_count = explode(':', $pair);
        $new_text_ids{$nid_and_count[0]} = $nid_and_count[1];
      }
      foreach ($old_text_ids as $id => $count) {
        if (isset($new_text_ids[$id])) {
          $merged_text_ids[$id] = $new_text_ids[$id] + $old_text_ids[$id];
        }
        else {
          $merged_text_ids[$id] = $old_text_ids[$id];
        }
      }
      foreach ($new_text_ids as $id => $count) {
        if (!isset($old_text_ids[$id])) {
          $merged_text_ids[$id] = $new_text_ids[$id];
        }
      }
      $concat = [];
      foreach ($merged_text_ids as $id => $count) {
        $concat[] = $id . ":" . $count;
        $ready_list = implode(',', $concat);
      }
    }
    else {
      $ready_list = $texts;
    }
    $connection = \Drupal::database();
    $connection->merge('corpus_lemma_frequency')
      ->key(['word' => utf8_decode($lemma)])
      ->fields([
        'ids' => $ready_list,
      ])
      ->execute();
    if ($word != $lemma) {
      return $word . '=>' . $lemma;
    }
    return $word;

  }

  /**
   * The main lemmatizing function.
   */
  public static function lemmatize($word) {
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('search_api_lemma')->getPath();
    $alpha = $word[0];
    $path = DRUPAL_ROOT . '/' . $module_path . '/data/lemmas_' . $alpha . '.php';
    if (file_exists($path)) {
      require $path;
      if (isset($lemma_map[$word])) {
        return $lemma_map[$word];
      }
    }
    return $word;
  }

  public static function getVariants($lemma) {
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('search_api_lemma')->getPath();
    // Make sure the original value is included!
    $tokens = [$lemma];
    $path = DRUPAL_ROOT . '/' . $module_path . '/data/roots_' . $lemma[0] . '.php';
    if (file_exists($path)) {
      require $path;
    }
    // Get lemma variants for highlighting in search excerpts.
    if (isset($root_map[$lemma])) {
      $lemmas = explode(',', $root_map[$lemma]);
      // Add original root!
      $lemmas[] = $lemma;
      usort($lemmas, function($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
      });
      $tokens = $lemmas;
    }
    return $tokens;
  }

  /**
   * Callback function to truncate the table.
   */
  public static function wipe() {
    $connection = \Drupal::database();
    $query = $connection->delete('corpus_lemma_frequency');
    $query->execute();
  }

}
