<?php

namespace Drupal\corpus_frequency\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\corpus_frequency\FrequencyHelper;
use Symfony\Component\HttpFoundation\Request;

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
    $results = self::getFrequency($request);
    $response = new CacheableJsonResponse([], 200);
    $response->setContent(json_encode($results));
    $response->headers->set('Content-Type', 'application/json');
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

  /**
   * The method called by the REST endpoint (and by self::endpoint(), above).
   */
  public static function getFrequency($request) {
    // Response.
    $category = $request->query->get('category');
    $name = $request->query->get('name');
    if (!isset($name) || !isset($category)) {
      \Drupal::logger('corpus_frequency')->notice('Invalid query');
      $results = [];
    }
    else {
      if ($category === 'course') {
        // Change legacy courses to new IDs (e.g., ENGL 101 --> ENGL 101-UA).
        $request->query->set('name', ImporterHelper::getLegacyInstitutionalCourse($name));
      }
      // Check for presence of cached data.
      $cache_id = self::getCacheString($request);
      if ($cache = \Drupal::cache()->get($cache_id)) {
        if ($cache->expire > time()) {
          return $cache->data;
        }
      }
      $results = FrequencyHelper::analyze($name, $category);
      \Drupal::cache()->set($cache_id, $results, \Drupal::time()->getRequestTime() + (2500000));
    }
    return $results;
  }

  /**
   * Helper function to get a specific cache id.
   */
  private static function getCacheString($request) {
    $cachestring = 'corpus_frequency_subset_' . $request->getRequestUri();
    return md5($cachestring);
  }

}
