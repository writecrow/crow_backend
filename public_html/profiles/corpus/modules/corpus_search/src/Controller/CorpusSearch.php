<?php

namespace Drupal\corpus_search\Controller;

use Drupal\corpus_importer\ImporterHelper;
use Drupal\corpus_search\SearchService as Search;
use Drupal\corpus_search\CorpusWordFrequency as Frequency;
use Drupal\corpus_search\TextMetadata;
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
class CorpusSearch extends ControllerBase {

  /**
   * The Controller endpoint -- for testing purposes.
   *
   * The actual REST endpoint is
   * Drupal\corpus_search\Plugin\rest\resource\CorpusSearch.
   */
  public static function endpoint(Request $request) {
    // Response.
    $results = self::getSearchResults($request);
    $response = new CacheableJsonResponse([], 200);
    // $response = new JsonResponse([], 200); .
    $response->setContent(json_encode($results));
    $response->headers->set('Content-Type', 'application/json');
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

  /**
   * The main function used for generating data.
   *
   * Provides an array of data for search results output & for CSV exporting.
   */
  public static function getSearchResults(Request $request) {
    // Change legacy courses to new IDs (e.g., ENGL 101 --> ENGL 101-UA).
    $course = $request->query->get('course');
    if ($course) {
      $request->query->set('course', ImporterHelper::getLegacyInstitutionalCourse($course));
    }
    // Check for presence of cached data.
    $cache_id = self::getCacheString($request);
    if ($cache = \Drupal::cache()->get($cache_id)) {
      if ($cache->expire > time()) {
        return $cache->data;
      }
    }
    $search_data = self::search($request);
    \Drupal::cache()->set($cache_id, $search_data['output'], \Drupal::time()->getRequestTime() + (2500000));
    return $search_data['output'];
  }

  /**
   * Given a search string in query parameters, return full results.
   */
  public static function search(Request $request) {
    // @todo: limit facet map to just Text matches (& cache?).
    $facet_map = TextMetadata::getFacetMap();
    // Get all facet/filter conditions.
    $conditions = self::getConditions($request->query->all(), $facet_map);
    if (!isset($conditions['filenames'])) {
      $offset = $request->query->get('offset') ?? 0;
    }
    $all_texts_metadata = TextMetadata::getAll();
    $ratio = 1;
    $excerpt_display = 'plain';
    $token_data = [];
    $op = 'or';
    $tokens = [];
    $matching_texts = [];
    $results = [
      'search_results' => [],
      'facets' => [],
      'pager' => [],
      'frequency' => [],
    ];
    $global = [
      'instance_count' => 0,
      'subcorpus_wordcount' => 0,
      'facet_counts' => [],
    ];
    // First, always run a conditions (facets) only search.
    // This is used both in the text search for narrowing and to calculate
    // the subcorpus wordcount.
    $condition_match_ids = Search::nonTextSearch($conditions);
    $condition_matches = array_intersect_key($all_texts_metadata, array_flip($condition_match_ids));
    foreach ($condition_matches as $t) {
      $global['subcorpus_wordcount'] += $t['wordcount'];
    }
    // If there is a search string, use this to refine results.
    if ($search_string = strip_tags(urldecode($request->query->get('search')))) {
      $excerpt_display = $request->query->get('display') ?? 'crowcordance';
      $tokens = self::getTokens($search_string);
      // Is this and "and" or "or" text search?
      $op = Xss::filter($request->query->get('op'));
      // Retrieve whether a 'lemma' search has been specified.
      $method = Xss::filter($request->query->get('method'));
      foreach ($tokens as $token => $type) {
        $individual_search = self::getIndividualSearchResults($token, $type, $condition_matches, $method);
        $token_data[$token] = $individual_search;
        $global = self::updateGlobalData($global, $individual_search, $op);
      }
      if (isset($global['text_ids'])) {
        $matching_texts = array_intersect_key($all_texts_metadata, $global['text_ids']);
      }
    }
    else {
      // Perform a non-keyword (i.e., only conditions such as facets) search.
      $global['text_ids'] = $condition_match_ids;
      $matching_texts = $condition_matches;
    }
    if ($op == 'and' && !empty($token_data)) {
      $updated_token_data = [];
      // Do additional counting operation for AND instance counts.
      foreach ($matching_texts as $id => $placeholder) {
        foreach ($token_data as $token => $data) {
          if (!isset($updated_token_data[$token])) {
            $updated_token_data[$token] = [
              'instance_count' => 0,
              'text_count' => 0,
              'text_ids' => [],
            ];
          }
          if (isset($data['text_ids'][$id])) {
            $updated_token_data[$token]['instance_count'] += $data['text_ids'][$id];
            $updated_token_data[$token]['text_count']++;
            $updated_token_data[$token]['text_ids'][$id] = 1;
            $global['instance_count'] += $data['text_ids'][$id];
          }
        }
      }
      $token_data = $updated_token_data;
    }

    // Get the subcorpus normalization ratio (per 1 million words).
    if (!empty($global['subcorpus_wordcount'])) {
      $ratio = 1000000 / $global['subcorpus_wordcount'];
    }
    if (!isset($matching_texts)) {
      $matching_texts = [];
    }
    $results['pager']['total_items'] = count($matching_texts);
    $results['pager']['subcorpus_wordcount'] = $global['subcorpus_wordcount'];
    $results['facets'] = TextMetadata::countFacets($matching_texts, $facet_map, $conditions);

    $excerpt_tokens = array_keys($tokens);
    // Get frequency data!
    // Loop through tokens once more, now that we know the subcorpus wordcount.
    if (!empty($token_data)) {
      foreach ($token_data as $t => $individual_data) {
        if ($method == 'lemma') {
          $lemma = CorpusLemmaFrequency::lemmatize($t);
          $variants = CorpusLemmaFrequency::getVariants($lemma);
          $excerpt_tokens = array_merge($excerpt_tokens, $variants);
          $t = implode('/', $variants);
        }
        $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
        $results['frequency']['tokens'][$t]['raw'] = $individual_data['instance_count'];
        $results['frequency']['tokens'][$t]['normed'] = $ratio * $individual_data['instance_count'];
        $results['frequency']['tokens'][$t]['texts'] = count($individual_data['text_ids']);
      }
      if (count($token_data) > 1) {
        $results['frequency']['totals']['raw'] = $global['instance_count'];
        $results['frequency']['totals']['normed'] = $ratio * $global['instance_count'];
        $results['frequency']['totals']['texts'] = count($global['text_ids']);
      }
    }
    // This runs after the frequency data to take advantage of the
    // updated $tokens, if any, from a lemma search.
    $results['search_results'] = Excerpt::getExcerpt($matching_texts, $excerpt_tokens, $facet_map, 20, $offset, $excerpt_display);
    // Build the output for use in the search data and for CSV exporting.
    $search_results['output'] = $results;
    $search_results['matching_texts'] = $matching_texts;
    $search_results['tokens'] = $excerpt_tokens;
    $search_results['facet_map'] = $facet_map;
    return $search_results;
  }

  /**
   * Calculate unique texts && subcorpus wordcount.
   */
  private static function updateGlobalData($global, $individual_search, $op = "or") {
    switch ($op) {
      case "and":
        if (!isset($global['text_ids'])) {
          $global['text_ids'] = [];
          // This is the first time through the search.
          // Set the global text IDs to the search results.
          foreach ($individual_search['text_ids'] as $id => $text_data) {
            // Get an exclusive list of all text ids matching search criteria.
            $global['text_ids'][$id] = 1;
          }
        }
        else {
          // Intersect search results.
          $current_global = array_keys($global['text_ids']);
          $global['text_ids'] = [];
          $current_search = array_keys($individual_search['text_ids']);
          $shared_ids = array_intersect($current_global, $current_search);
          foreach (array_values($shared_ids) as $id) {
            $global['text_ids'][$id] = 1;
          }
        }
        break;

      default:
        $global['instance_count'] = $global['instance_count'] + $individual_search['instance_count'];
        foreach ($individual_search['text_ids'] as $id => $text_data) {
          // Get a *combined* list of all text ids matching search criteria.
          $global['text_ids'][$id] = 1;
        }
        break;
    }
    return $global;
  }

  /**
   * Parse the query for user-supplied search parameters.
   */
  protected static function getConditions($parameters, $facet_map) {
    $conditions = [];
    foreach (array_keys(TextMetadata::$facetIDs) as $id) {
      if (isset($parameters[$id])) {
        $condition = self::fixEncodedCharacters(Xss::filter($parameters[$id]));
        $param_list = explode("+", $condition);
        foreach ($param_list as $param) {
          if (!empty($facet_map['by_name'][$id][$param])) {
            $conditions[$id][] = $facet_map['by_name'][$id][$param];
          }
        }
      }
    }
    if (isset($parameters['id'])) {
      $conditions['id'] = Xss::filter($parameters['id']);
    }
    if (isset($parameters['filenames'])) {
      // Convert filenames to array. If this is present, it will bypass facet filters, etc.
      $conditions['filenames'] = explode(' ', Xss::filter($parameters['filenames']));
    }
    if (isset($parameters['toefl_total_min'])) {
      $conditions['toefl_total_min'] = Xss::filter($parameters['toefl_total_min']);
    }
    if (isset($parameters['toefl_total_max'])) {
      $conditions['toefl_total_max'] = Xss::filter($parameters['toefl_total_max']);
    }

    return $conditions;
  }

  /**
   * Helper function to preserve certain characters in metadata.
   *
   * @param string $string
   *   A string of text.
   *
   * @return string
   *   The prepared string of text.
   */
  protected static function fixEncodedCharacters($string) {
    $string = str_replace('&amp;', '&', $string);
    return $string;
  }

  /**
   * Determine which type of search to perform.
   */
  public static function getTokens($search_string) {
    $result = [];
    $tokens = preg_split("/\"[^\"]*\"(*SKIP)(*F)|[ \/]+/", $search_string);
    if (!empty($tokens)) {
      // Determine whether to do a phrase or word search & case-sensitivity.
      foreach ($tokens as $token) {
        if (ctype_space($token)) {
          continue;
        }
        $length = strlen($token);
        if ((substr($token, 0, 1) == '"') && (substr($token, $length - 1, 1) == '"')) {
          $cleaned = substr($token, 1, $length - 2);
          if (preg_match("/[^a-zA-Z]/", $cleaned)) {
            // This is a quoted string. Do a phrasal search.
            $result[$token] = 'phrase';
          }
          else {
            // This is a case-sensitive word search.
            $result[$token] = 'quoted-word';
          }

        }
        else {
          // This is a word. Remove punctuation.
          $tokenized = Frequency::tokenize($token);
          $token = $tokenized[0];
          $result[strtolower($token)] = 'word';
        }
      }
    }
    return $result;
  }

  /**
   * Helper function to get a specific cache id.
   */
  private static function getCacheString($request) {
    $cachestring = 'corpus_search_output_' . $request->getRequestUri();
    return md5($cachestring);
  }

  /**
   * Helper method to direct the type of search to the search method.
   */
  protected static function getIndividualSearchResults($token, $type, $condition_matches, $method) {
    $data = [];
    switch ($type) {
      case 'phrase':
        $length = strlen($token);
        // Remove quotation marks.
        $cleaned = substr($token, 1, $length - 2);
        $data = Search::phraseSearch($cleaned, $condition_matches);
        break;

      case 'quoted-word':
        $length = strlen($token);
        $cleaned = substr($token, 1, $length - 2);
        $data = Search::wordSearch($cleaned, $condition_matches, 'sensitive');
        break;

      case 'word':
        $data = Search::wordSearch($token, $condition_matches, 'insensitive', $method);
        break;
    }
    return $data;
  }

}
