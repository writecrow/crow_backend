<?php

namespace Drupal\corpus_search\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;

/**
 * Corpus Search excerpt embed endpoint.
 *
 * @package Drupal\corpus_search\Controller
 */
class ExcerptEmbed extends CorpusSearch {

  /**
   * The Controller endpoint -- for testing purposes.
   *
   * The actual REST endpoint is
   * Drupal\corpus_search\Plugin\rest\resource\CorpusSearch.
   */
  public static function endpoint(Request $request) {
    // Response.
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('corpus_search')->getPath();
    $response = new CacheableResponse('', 200);
    $renderer = \Drupal::service('renderer');

    $output = self::getResults($request);
    $sorted_before_array = [
      '#type' => 'table',
      '#rows' => $output['sorted_before'],
      '#header_columns' => 1,
    ];
    $sorted_after_array = [
      '#type' => 'table',
      '#rows' => $output['sorted_after'],
      '#header_columns' => 1,
    ];
    $sorted_before = $renderer->renderPlain($sorted_before_array);
    $sorted_after = $renderer->renderPlain($sorted_after_array);
    $build = [
      'page' => [
        '#theme' => 'corpus_concordance',
        '#sorted_before' => json_encode($sorted_before),
        '#sorted_after' => json_encode($sorted_after),
        '#css' => file_get_contents($module_path . '/css/concordance_lines.css'),
        '#script' => file_get_contents($module_path . '/js/concordance_lines.js'),
      ],
    ];
    $html = \Drupal::service('renderer')->renderRoot($build);
    $response->setContent($html);
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

  private static function getResults($request) {
    $numbering = \Drupal::request()->query->get('numbering');
    $results = self::getSearchResults($request, "fixed");
    $output = [];
    $table = [];
    if (!empty($results['search_results'])) {
      $inc = 0;
      foreach ($results['search_results'] as $result) {
        if ($inc > 19) {
          break;
        }
        $inc++;
        $table[$inc]['number'] = '';
        // Put the highlighted keyword into an array.
        preg_match('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], $keyword_matches);
        $bookends = preg_split('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], 2);
        preg_match('/[^ ]*$/', trim($bookends[0]), $preceding);
        $before_split = preg_split('/[^ ]*$/', trim($bookends[0]));
        $table[$inc]['start'] = $before_split[0] ?: '';
        $table[$inc]['word_before'] = $preceding[0] ?: '';
        $table[$inc]['keyword'] = $keyword_matches[0];
        preg_match('/(\s*)([^\s]*)(.*)/', $bookends[1], $following);
        $table[$inc]['after'] = $following[0] ?: '';

        // Add spacing to visually make up for short start sections.
        $before_length = mb_strlen($table[$inc]['word_before']);
        $start_length = mb_strlen($table[$inc]['start']);
        if (($before_length + $start_length) < 60) {
          $makeup = 60 - ($before_length + $start_length);
          $table[$inc]['start'] = str_repeat("&nbsp;", $makeup) . $table[$inc]['start'];
        }
      }
      usort($table, function ($a, $b) {
        return mb_strtolower($a['word_before']) <=> mb_strtolower($b['word_before']);
      });
      $sorted = [];
      $sorted['sorted_before'] = $table;
      usort($table, function ($a, $b) {
        return mb_strtolower($a['after']) <=> mb_strtolower($b['after']);
      });
      $sorted['sorted_after'] = $table;

      $inc = 0;
      foreach ($sorted['sorted_before'] as $key => $values) {
        $inc++;
        $number = $numbering == 1 ? $inc : '';
        $output['sorted_before'][] = [
          $number,
          Markup::create($values['start'] . $values['word_before'] . '&nbsp;' . $values['keyword'] . $values['after']),
        ];
      }
      $inc = count($sorted['sorted_after']);
      foreach ($sorted['sorted_after'] as $key => $values) {
        $number = $numbering == 1 ? $inc : '';
        $output['sorted_after'][] = [
          $number,
          Markup::create($values['start'] . $values['word_before'] . '&nbsp;' . $values['keyword'] . $values['after']),
        ];
        $inc--;
      }
    }
    return $output;
  }

}
