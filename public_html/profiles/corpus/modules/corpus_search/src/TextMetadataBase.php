<?php

namespace Drupal\corpus_search;

/**
 * Class TextMetadataBase.
 *
 * @package Drupal\corpus_search
 */
abstract class TextMetadataBase {

  public static $facetIDs = [
    'assignment' => 'at',
    'authorship' => 'au',
    'college' => 'co',
    'country' => 'cy',
    'course' => 'ce',
    'draft' => 'dr',
    'gender' => 'ge',
    'institution' => 'it',
    'program' => 'pr',
    'semester' => 'se',
    'year' => 'yr',
    'year_in_school' => 'ys',
    'l1' => 'l1',
  ];

  public static $corpusSourceBundle = 'text';

  /**
   * {@inheritdoc}
   */
  public static $body_field = 'field_body';

  /**
   * {@inheritdoc}
   */
  public static $metadata_groups = [
    'filename',
    'institution',
    'course',
    'authorship',
    'assignment',
    'program',
    'college',
    'draft',
    'gender',
    'semester',
    'year',
    'toefl_total',
  ];

  public static function getAll() {
    $metadata_cid = 'corpus_search_all_metadata';
    $cache_id = md5($metadata_cid);
    if ($cache = \Drupal::cache()->get($cache_id)) {
      return $cache->data;
    }
    else {
      $metadata = self::batchMetadata();
      \Drupal::cache()->set($cache_id, $metadata, \Drupal::time()->getRequestTime() + (2500000));
      return $metadata;
    }
  }

  /**
   * Retrieve metadata for all texts in batch.
   */
  public static function batchMetadata() {
    // Set the number of items to process at a time.
    $limit = 100;
    $connection = \Drupal::database();
    $metadata = [];

    // Total nodes that must be visited.
    $query = \Drupal::entityQuery('node')
      ->condition('type', self::$corpusSourceBundle);
    $total = $query->count()->execute();

    $offset = 0;
    while ($offset < $total) {
      print_r($offset . PHP_EOL);
      // Build the query (with the Batch limit!).
      $query = $connection->select('node_field_data', 'n');
      $query->condition('n.type', self::$corpusSourceBundle, '=');
      $query->fields('n', ['title', 'type', 'nid']);
      // Add non-facet fields.
      $query->leftJoin('node__field_id', 'id', 'n.nid = id.entity_id');
      $query->fields('id', ['field_id_value']);
      if (in_array('toefl_total', TextMetadata::$metadata_groups)) {
        $query->leftJoin('node__field_toefl_total', 'tt', 'n.nid = tt.entity_id');
        $query->fields('tt', ['field_toefl_total_value']);
      }
      $query->leftJoin('node__field_wordcount', 'wc', 'n.nid = wc.entity_id');
      $query->fields('wc', ['field_wordcount_value']);
      foreach (self::$facetIDs as $field => $alias) {
        $query->leftJoin('node__field_' . $field, $alias, 'n.nid = ' . $alias . '.entity_id');
        $query->fields($alias, ['field_' . $field . '_target_id']);
      }
      $query->range($offset, $limit);
      $result = $query->execute();
      $matching_texts = $result->fetchAll();
      if (!empty($matching_texts)) {
        foreach ($matching_texts as $result) {
          $metadata[$result->nid] = self::populateTextMetadata($result);
        }
      }
      $offset = $offset + $limit;
    }
    print_r(count($metadata));
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
      foreach (array_keys(self::$facetIDs) as $group) {
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
    }
    // Add facets that have no matches to the result set.
    // Loop through facet groups (e.g., course, assignment).
    foreach (array_keys(self::$facetIDs) as $group) {
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
    ];
    if (in_array('toefl_total', TextMetadata::$metadata_groups)) {
      $metadata['toefl_total'] = $result->field_toefl_total_value;
    }
    foreach (array_keys(self::$facetIDs) as $field) {
      $target = 'field_' . $field . '_target_id';
      if (isset($result->$target)) {
        $metadata[$field] = $result->$target;
      }
    }
    return $metadata;
  }

}
