<?php

namespace Drupal\corpus_search;

use writecrow\Highlighter\Highlighter;

/**
 * Class Excerpt.
 *
 * @package Drupal\corpus_search
 */
class Excerpt {

  /**
   * Helper function.
   *
   * @param string[] $matching_texts
   *   An array of entity data, including metadata.
   * @param string[] $tokens
   *   The words/phrases to be highlighted.
   * @param string[] $facet_map
   *   The canonical facet map -- includes all data.
   * @param int $limit
   *   The number of results to return.
   * @param int $offset
   *   Pagination functionality (translates to SQL offset).
   * @param string $excerpt_display
   *   Provide concatenated results or keyed (for iDDL)?
   */
  public static function getExcerpt(array $matching_texts, array $tokens, array $facet_map, $limit = 20, $offset = 0, $excerpt_display = "concat") {
    if (empty($matching_texts)) {
      return [];
    }
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n')
      ->fields('n', ['entity_id', 'text'])
      ->condition('n.entity_id', array_keys($matching_texts), 'IN');
    $query->range($offset, $limit);
    $results = $query->execute()->fetchAllKeyed();
    $sliced_matches = array_intersect_key($matching_texts, $results);
    foreach ($sliced_matches as $id => $metadata) {
      $excerpts[$id]['filename'] = $metadata['filename'];
      $excerpts[$id]['wordcount'] = $metadata['wordcount'];
      // Check if the metadata includes a description & append that.
      foreach (TextMetadataConfig::$metadata_groups as $field) {
        if (isset($metadata[$field])) {
          $facet_data = self::getFacetData($metadata[$field], $field, $facet_map);
          $excerpts[$id][$field] = $facet_data['name'];
          if (isset($facet_data['description'])) {
            $excerpts[$id][$field . '_description'] = $facet_data['description'];
          }
        }
      }
      if ($excerpt_display === 'plain') {
        $excerpts[$id]['text'] = self::generatePlainExcerpt($results[$id]);
      }
      else {
        $excerpts[$id]['text'] = Highlighter::process($results[$id], $tokens, FALSE, $excerpt_display);
      }
    }
    return array_values($excerpts);
  }

  public static function generatePlainExcerpt($body) {
    $excerpt = '';
    $separator = "\r\n";
    $line = strtok($body, $separator);
    while ($line !== FALSE) {
      $line = strtok($separator);
      if (mb_strlen($excerpt) > 300) {
        return mb_substr($excerpt, 0, 300) . '...';
      }
      if (mb_strlen($line) < 50) {
        continue;
      }
      $excerpt .= $line . ' ';
    }
  }

  /**
   * Simple facet name array lookup.
   */
  public static function getFacetData($id, $vocabulary, $facet_map) {
    if (!empty($facet_map['by_id'][$vocabulary][$id])) {
      return $facet_map['by_id'][$vocabulary][$id];
    }
    return ['name' => $id];
  }

}
