<?php

namespace Drupal\corpus_search;

/**
 * Class TextMetadata.
 *
 * @package Drupal\corpus_search
 */
class TextMetadataConfig {

  public static $corpusSourceBundle = 'text';

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

}
