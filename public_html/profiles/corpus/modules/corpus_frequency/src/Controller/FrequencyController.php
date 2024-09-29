<?php

namespace Drupal\corpus_frequency\Controller;

use Drupal\corpus_importer\ImporterHelper;
use Drupal\corpus_search\SearchService as Search;
use Drupal\corpus_search\CorpusWordFrequency as Frequency;
use Drupal\corpus_search\TextMetadata;
use Drupal\corpus_search\TextMetadataConfig;
use Drupal\corpus_search\Excerpt;
use Drupal\corpus_search\CorpusLemmaFrequency;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Component\Utility\Xss;

/**
 * Corpus Search endpoint.
 *
 * @package Drupal\corpus_search\Controller
 */
class FrequencyController extends ControllerBase {

  /**
   * The Controller endpoint -- for testing purposes.
   */
  public static function endpoint(Request $request) {
    // Response.
    //$results = self::getSearchResults($request);
    $results = ['one', 'two', 'three'];
    $response = new CacheableJsonResponse([], 200);
    $response->setContent(json_encode($results));
    $response->headers->set('Content-Type', 'application/json');
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

}
