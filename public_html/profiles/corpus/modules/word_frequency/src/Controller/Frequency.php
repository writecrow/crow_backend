<?php

namespace Drupal\word_frequency\Controller;

use Drupal\word_frequency\FrequencyService;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Cache\CacheableJsonResponse;

/**
 * Class Frequency.
 *
 * @package Drupal\word_frequency\Controller
 */
class Frequency extends ControllerBase {

  /**
   * Given a search string, tokenize & return frequency data.
   */
  public function search(Request $request) {
    $search = \Drupal::request()->query->get('search');
    // The following regular expression is based on https://stackoverflow.com/questions/25004629/regex-preg-split-how-do-i-split-based-on-a-delimiter-excluding-delimiters-in
    $tokens = preg_split("/\"[^\"]*\"(*SKIP)(*F)|[ \/]+/", $search);
    if (!empty($tokens)) {
      $total = FrequencyService::totalWords();
      $ratio = 1000000 / $total;
      $output = ['tokens' => []];
      $prepared = [];
      // Determine whether to do a phrase search or word search
      // & case-sensitivity.
      foreach ($tokens as $token) {
        $length = strlen($token);
        if ((substr($token, 0, 1) == '"') && (substr($token, $length - 1, 1) == '"')) {
          $cleaned = substr($token, 1, $length - 2);
          if (preg_match("/[^a-zA-Z]/", $cleaned)) {
            // This is a quoted string. Do a phrasal search.
            $prepared[$token] = 'phrase';
          }
          else {
            // This is a case-sensitive word search.
            $prepared[$token] = 'quoted-word';
          }

        }
        else {
          // This is a word. Remove punctuation.
          $tokenized = FrequencyService::tokenize($token);
          $token = $tokenized[0];
          $prepared[strtolower($token)] = 'word';
        }
      }

      // Preparation.
      if (count($prepared) > 1) {
        $output['totals'] = ['texts' => '0'];
        $unique_texts = [];
      }
      // Retrieve counts.
      foreach ($prepared as $token => $type) {
        switch ($type) {
          case 'phrase':
            $length = strlen($token);
            $cleaned = substr($token, 1, $length - 2);
            $data = FrequencyService::phraseSearch($cleaned);
            break;

          case 'quoted-word':
            $length = strlen($token);
            $cleaned = substr($token, 1, $length - 2);
            $data = FrequencyService::simpleSearch($cleaned, 'sensitive');
            break;

          case 'word':
            $data = FrequencyService::simpleSearch($token);
            break;
        }

        $data['normed'] = $data['raw'] * $ratio;
        $texts = count(array_unique($data['ids']));
        if (count($prepared) > 1) {
          $unique_texts = array_unique(array_merge($unique_texts, $data['ids']));
          $output['totals']['raw'] = $output['totals']['raw'] + $data['raw'];
          $output['totals']['normed'] = $output['totals']['normed'] + $data['normed'];
        }
        $output['tokens'][] = [
          'token' => $token,
          'raw' => $data['raw'],
          'normed' => $data['normed'],
          'texts' => $texts,
          'excerpts' => $data['excerpts'],
        ];
      }
      if (count($prepared) > 1) {
        $output['totals']['texts'] = count($unique_texts);
      }
    }

    // Response.
    $response = new CacheableJsonResponse([], 200);
    $response->setContent(json_encode($output));
    $response->headers->set('Content-Type', 'application/json');
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

  /**
   * Given a search string, tokenize & return frequency data.
   */
  public function wordSearch(Request $request) {
    $response = new Response();
    $case = 'insensitive';
    $total = FrequencyService::totalWords();
    $ratio = 10000 / $total;
    $search = \Drupal::request()->query->get('search');
    // First check for quoted terms.
    $pieces = explode(" ", $search);
    $prepared = [];
    foreach ($pieces as $piece) {
      $length = strlen($piece);
      if ((substr($piece, 0, 1) == '"') && (substr($piece, $length - 1, 1) == '"')) {
        $cleaned = substr($piece, 1, $length - 2);
        $prepared[$cleaned] = 'quoted';
      }
      else {
        $prepared[strtolower($piece)] = 'standard';
      }
    }
    foreach ($prepared as $word => $type) {
      $search = FrequencyService::tokenize($word);
      if ($type == 'quoted') {
        $count = FrequencyService::simpleSearch($search[0], 'sensitive');
        $term = '"' . $search[0] . '"';
      }
      else {
        $count = FrequencyService::simpleSearch($search[0]);
        $term = $search[0];
      }
      $result[$term]['raw'] = $count['count'];
      $result[$term]['normed'] = number_format($count['count'] * $ratio);
      $result[$term]['texts'] = $count['texts'];
    }
    $output['terms'] = $result;
    // Only run if multiple words have been supplied.
    if (count($result) > 1) {
      $totals['raw'] = 0;
      $totals['normed'] = 0;
      $totals['texts'] = 0;
      foreach ($result as $i) {
        $totals['raw'] = $totals['raw'] + $i['raw'];
        $totals['normed'] = $totals['normed'] + $i['normed'];
      }
      // Get total texts containing at least 1 instance of any of the words.
      $totals['texts'] = FrequencyService::countTextsContaining($prepared);
      $output['totals'] = $totals;
    }
    $response->setContent(json_encode($output));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  /**
   * Given a search string, return non-tokenized frequency data.
   */
  public function phraseSearch(Request $request) {
    $response = new Response();
    $count = [];
    if ($search = \Drupal::request()->query->get('search')) {
      $count = FrequencyService::phraseSearch($search);
    }
    $response->setContent(json_encode($count));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  /**
   * Return the current wordcount of the entire corpus.
   */
  public function totalWords() {
    $count = FrequencyService::totalWords();
    $response = new CacheableJsonResponse([], 200);
    $response->setContent(json_encode(array('total' => $count)));
    $response->headers->set('Content-Type', 'application/json');
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

}
