<?php

namespace Drupal\corpus_search;

use writecrow\Highlighter\HighlightExcerpt;

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
   * @param bool $do_excerpt
   *   Should an excerpt be returned?
   * @param string $excerpt_type
   *   Provide concatenated results or keyed (for iDDL)?
   */
  public static function getExcerptOrFullText(array $matching_texts, array $tokens, array $facet_map, $limit = 20, $offset = 0, $do_excerpt = TRUE, $excerpt_type = "concat") {
    if (empty($matching_texts)) {
      return [];
    }
    $connection = \Drupal::database();
    $query = $connection->select('node__field_body', 'n')
      ->fields('n', ['entity_id', 'field_body_value'])
      ->condition('n.entity_id', array_keys($matching_texts), 'IN');
    $query->range($offset, $limit);
    $results = $query->execute()->fetchAllKeyed();
    $sliced_matches = array_intersect_key($matching_texts, $results);
    // @see TextMetadata::populateTextMetadata().
    $metadata_groups = [
      'filename',
      'institution',
      'course',
      'assignment',
      'program',
      'college',
      'draft',
      'gender',
      'semester',
      'year',
      'toefl_total',
    ];
    foreach ($sliced_matches as $id => $metadata) {
      $excerpts[$id]['filename'] = $metadata['filename'];
      $excerpts[$id]['wordcount'] = $metadata['wordcount'];
      // Check if the metadata includes a description & append that.
      foreach ($metadata_groups as $field) {
        if (isset($metadata[$field])) {
          $facet_data = self::getFacetData($metadata[$field], $field, $facet_map);
          $excerpts[$id][$field] = $facet_data['name'];
          if (isset($facet_data['description'])) {
            $excerpts[$id][$field . '_description'] = $facet_data['description'];
          }
        }
      }
      if ($do_excerpt) {
        $excerpts[$id]['text'] = HighlightExcerpt::highlight($results[$id], $tokens, '300', $excerpt_type) . '...';
      }
      else {
        $excerpts[$id]['text'] = $results[$id];
      }
    }
    return array_values($excerpts);
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
