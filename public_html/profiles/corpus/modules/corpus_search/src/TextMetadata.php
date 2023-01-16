<?php

namespace Drupal\corpus_search;

/**
 * Class TextMetadata.
 *
 * @package Drupal\corpus_search
 */
class TextMetadata extends TextMetadataBase {

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
