<?php

namespace Drupal\corpus_search;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class TextMetadataBase.
 *
 * @package Drupal\corpus_search
 */
class TextMetadata {

  public static $metadata_cache_id = 'corpus_metadata_all_search';

  public static function getAll() {
    if ($cache = \Drupal::cache()->get(self::$metadata_cache_id)) {
      return $cache->data;
    }
    else {
      return self::batchMetadata();
    }
  }

  /**
   * Retrieve metadata for all texts in batch.
   */
  public static function batchMetadata() {
    $connection = \Drupal::database();
    $metadata = [];

    $query = $connection->select('node_field_data', 'n');
    $query->condition('type', TextMetadataConfig::$corpusSourceBundle, '=');
    $query->fields('n', ['title', 'type', 'nid']);
    //Add non-facet fields.
    $query->join('node__field_id', 'id', 'n.nid = id.entity_id');
    $query->fields('id', ['field_id_value']);
    if (in_array('toefl_total', TextMetadataConfig::$metadata_groups)) {
      $query->leftJoin('node__field_toefl_total', 'tt', 'n.nid = tt.entity_id');
      $query->fields('tt', ['field_toefl_total_value']);
    }
    $query->leftJoin('node__field_wordcount', 'wc', 'n.nid = wc.entity_id');
    $query->fields('wc', ['field_wordcount_value']);
    $query->leftJoin('node__field_first_and_final', 'ff', 'n.nid = ff.entity_id');
    $query->fields('ff', ['field_first_and_final_value']);
    foreach (TextMetadataConfig::$facetIDs as $field => $alias) {
      $query->leftJoin('node__field_' . $field, $alias, 'n.nid = ' . $alias . '.entity_id');
      $query->fields($alias, ['field_' . $field . '_target_id']);
    }
    // Total nodes that must be visited.
    $total = $query->distinct()->countQuery()->execute()->fetchField();
    $offset = 0;
    // Set the number of items to process at a time.
    $limit = 10000;
    while ($offset < $total) {
      $query->range($offset, $limit);
      $result = $query->distinct()->execute();
      $matching_texts = $result->fetchAll();
      if (!empty($matching_texts)) {
        foreach ($matching_texts as $result) {
          $metadata[$result->nid] = self::populateTextMetadata($result);
        }
      }
      $offset = $offset + $limit;
    }
    \Drupal::cache()->set(self::$metadata_cache_id, $metadata, CacheBackendInterface::CACHE_PERMANENT);
    return $metadata;
  }

  /**
   * Get map of term name-id relational data.
   */
  public static function getFacetMap() {
    $map = [];
    $connection = \Drupal::database();
    $query = $connection->select('taxonomy_term_field_data', 't');
    $query->fields('t', ['tid', 'vid', 'name', 'description__value']);
    $result = $query->execute()->fetchAll();
    foreach ($result as $i) {
      $data = [
        'name' => $i->name,
      ];
      if (isset($i->description__value)) {
        $data['description'] = strip_tags($i->description__value);
      }
      $map['by_name'][$i->vid][$i->name] = $i->tid;
      $map['by_id'][$i->vid][$i->tid] = $data;
    }
    return $map;
  }

  /**
   * Loop through the facets & increment each item's count.
   */
  public static function countFacets($matching_texts, $facet_map, $conditions) {
    $facet_results = [];
    foreach ($matching_texts as $id => $elements) {
      foreach (array_keys(TextMetadataConfig::$facetIDs) as $group) {
        if (isset($elements[$group]) && isset($facet_map['by_id'][$group][$elements[$group]])) {
          $name = $facet_map['by_id'][$group][$elements[$group]]['name'];
          if (!isset($facet_results[$group][$name]['count'])) {
            $facet_results[$group][$name]['count'] = 1;
          }
          else {
            $facet_results[$group][$name]['count']++;
          }
        }
      }
      if ($elements['first_and_final'] === '1') {
        if (!isset($facet_results['draft']['Has first and final draft'])) {
          $facet_results['draft']['Has first and final draft']['count'] = 1;
        }
        else {
          $facet_results['draft']['Has first and final draft']['count']++;
        }
      }
    }

    // Add facets that have no matches to the result set.
    // Loop through facet groups (e.g., course, assignment).
    foreach (array_keys(TextMetadataConfig::$facetIDs) as $group) {
      // Loop through facet names (e.g., ENGL 106, ENGL 107).
      foreach ($facet_map['by_name'][$group] as $name => $id) {
        if (!isset($facet_results[$group][$name])) {
          $facet_results[$group][$name] = ['count' => 0];
        }
        if (isset($conditions[$group]) && in_array($id, $conditions[$group])) {
          $facet_results[$group][$name]['active'] = TRUE;
        }
        if (isset($facet_results[$group][$name])) {
          // Add description, if it exists..
          if (isset($facet_map['by_id'][$group][$id]['description'])) {
            $facet_results[$group][$name]['description'] = $facet_map['by_id'][$group][$id]['description'];
          }
        }
      }
      // Ensure facets are listed alphanumerically.
      if (isset($facet_results[$group])) {
        ksort($facet_results[$group]);
      }
    }
    return $facet_results;
  }

  /**
   * Helper function to put a single text's result data into a structured array.
   */
  private static function populateTextMetadata($result) {
    $metadata = [
      'filename' => $result->title,
      'wordcount' => $result->field_wordcount_value,
      'first_and_final' => $result->field_first_and_final_value,
    ];
    if (in_array('toefl_total', TextMetadataConfig::$metadata_groups)) {
      $metadata['toefl_total'] = $result->field_toefl_total_value;
    }
    foreach (array_keys(TextMetadataConfig::$facetIDs) as $field) {
      $target = 'field_' . $field . '_target_id';
      if (isset($result->$target)) {
        $metadata[$field] = $result->$target;
      }
    }
    return $metadata;
  }

}
