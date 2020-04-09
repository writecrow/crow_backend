<?php

namespace Drupal\corpus_search;

/**
 * Class SearchService.
 *
 * @package Drupal\corpus_search
 */
class TextMetadata {

  public static $facetIDs = [
    'assignment' => 'at',
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
  ];

  /**
   * Retrieve metadata for all texts in one go!
   */
  public static function getAll() {
    $cache_id = md5('corpus_search_all_texts');
    if ($cache = \Drupal::cache()->get($cache_id)) {
      return $cache->data;
    }
    $connection = \Drupal::database();
    $query = $connection->select('node_field_data', 'n');
    $query->leftJoin('node__field_assignment', 'at', 'n.nid = at.entity_id');
    $query->leftJoin('node__field_college', 'co', 'n.nid = co.entity_id');
    $query->leftJoin('node__field_country', 'cy', 'n.nid = cy.entity_id');
    $query->leftJoin('node__field_course', 'ce', 'n.nid = ce.entity_id');
    $query->leftJoin('node__field_draft', 'dr', 'n.nid = dr.entity_id');
    $query->leftJoin('node__field_gender', 'ge', 'n.nid = ge.entity_id');
    $query->leftJoin('node__field_id', 'id', 'n.nid = id.entity_id');
    $query->leftJoin('node__field_institution', 'it', 'n.nid = it.entity_id');
    $query->leftJoin('node__field_program', 'pr', 'n.nid = pr.entity_id');
    $query->leftJoin('node__field_semester', 'se', 'n.nid = se.entity_id');
    $query->leftJoin('node__field_toefl_total', 'tt', 'n.nid = tt.entity_id');
    $query->leftJoin('node__field_year_in_school', 'ys', 'n.nid = ys.entity_id');
    $query->leftJoin('node__field_year', 'yr', 'n.nid = yr.entity_id');
    $query->leftJoin('node__field_wordcount', 'wc', 'n.nid = wc.entity_id');
    $query->fields('n', ['title', 'type', 'nid']);
    $query->fields('at', ['field_assignment_target_id']);
    $query->fields('co', ['field_college_target_id']);
    $query->fields('cy', ['field_country_target_id']);
    $query->fields('ce', ['field_course_target_id']);
    $query->fields('dr', ['field_draft_target_id']);
    $query->fields('ge', ['field_gender_target_id']);
    $query->fields('id', ['field_id_value']);
    $query->fields('it', ['field_institution_target_id']);
    $query->fields('pr', ['field_program_target_id']);
    $query->fields('se', ['field_semester_target_id']);
    $query->fields('tt', ['field_toefl_total_value']);
    $query->fields('ys', ['field_year_in_school_target_id']);
    $query->fields('yr', ['field_year_target_id']);
    $query->fields('wc', ['field_wordcount_value']);
    $query->condition('n.type', 'text', '=');
    $result = $query->execute();
    $matching_texts = $result->fetchAll();
    $texts = [];
    if (!empty($matching_texts)) {
      foreach ($matching_texts as $result) {
        $texts[$result->nid] = self::populateTextMetadata($result);
      }
    }
    \Drupal::cache()->set($cache_id, $texts, REQUEST_TIME + (2500000));
    return $texts;
  }

  /**
   * Get map of term name-id relational data.
   */
  public static function getFacetMap() {
    $map = [];
    $connection = \Drupal::database();
    $query = $connection->select('taxonomy_term_field_data', 't');
    $query->fields('t', ['tid', 'vid', 'name']);
    $result = $query->execute()->fetchAll();
    foreach ($result as $i) {
      $map['by_name'][$i->vid][$i->name] = $i->tid;
      $map['by_id'][$i->vid][$i->tid] = $i->name;
    }
    return $map;
  }

  /**
   * Loop through the facets & increment each item's count.
   */
  public static function countFacets($matching_texts, $facet_map, $conditions) {
    foreach ($matching_texts as $id => $elements) {
      foreach (array_keys(self::$facetIDs) as $f) {
        if (isset($facet_map['by_id'][$f]{$elements[$f]})) {
          $name = $facet_map['by_id'][$f]{$elements[$f]};
          if (!isset($facet_results[$f][$name]['count'])) {
            $facet_results[$f][$name]['count'] = 1;
          }
          else {
            $facet_results[$f][$name]['count']++;
          }
        }
      }
    }
    // Add facets that have no matches to the result set.
    // Loop through facet groups (e.g., course, assignment).
    foreach (array_keys(self::$facetIDs) as $f) {
      // Loop through facet names (e.g., ENGL 106, ENGL 107).
      foreach ($facet_map['by_id'][$f] as $n) {
        if (!isset($facet_results[$f][$n])) {
          $facet_results[$f][$n]['count'] = 0;
        }
        $facet_id = $facet_map['by_name'][$f][$n];
        if (isset($conditions[$f])) {
          if (in_array($facet_id, $conditions[$f])) {
            $facet_results[$f][$n]['active'] = TRUE;
          }
        }
      }
      // Ensure facets are listed alphanumerically.
      ksort($facet_results[$f]);
    }
    return $facet_results;
  }

  /**
   * Helper function to put a single text's result data into a structured array.
   */
  private static function populateTextMetadata($result) {
    return [
      'filename' => $result->title,
      'assignment' => $result->field_assignment_target_id,
      'college' => $result->field_college_target_id,
      'country' => $result->field_country_target_id,
      'course' => $result->field_course_target_id,
      'draft' => $result->field_draft_target_id,
      'gender' => $result->field_gender_target_id,
      'institution' => $result->field_institution_target_id,
      'program' => $result->field_program_target_id,
      'semester' => $result->field_semester_target_id,
      'toefl_total' => $result->field_toefl_total_value,
      'year' => $result->field_year_target_id,
      'year_in_school' => $result->field_year_in_school_target_id,
      'wordcount' => $result->field_wordcount_value,
    ];
  }

}
