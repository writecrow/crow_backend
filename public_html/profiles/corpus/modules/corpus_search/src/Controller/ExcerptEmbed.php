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
    $display = $request->query->get('display') ?? 'kwic';

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
    $target_asc_array = [
      '#type' => 'table',
      '#rows' => $output['target_asc'],
      '#header_columns' => 1,
    ];
    $target_desc_array = [
      '#type' => 'table',
      '#rows' => $output['target_desc'],
      '#header_columns' => 1,
    ];
    $sorted_before = $renderer->renderInIsolation($sorted_before_array);
    $sorted_after = $renderer->renderInIsolation($sorted_after_array);
    $target_asc = $renderer->renderInIsolation($target_asc_array);
    $target_desc = $renderer->renderInIsolation($target_desc_array);
    $build = [
      'page' => [
        '#theme' => 'corpus_concordance',
        '#sorted_before' => json_encode($sorted_before),
        '#sorted_after' => json_encode($sorted_after),
        '#target_desc' => json_encode($target_desc),
        '#target_asc' => json_encode($target_asc),
        '#css' => file_get_contents($module_path . '/css/concordance_lines.css'),
        '#script' => file_get_contents($module_path . '/js/concordance_lines.js'),
      ],
    ];
    if ($display === 'crowcordance') {
      $build['page']['#css'] = file_get_contents($module_path . '/css/crowcordance.css');
    }
    $html = \Drupal::service('renderer')->renderRoot($build);
    $response->setContent($html);
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

  private static function getResults($request) {
    $numbering = \Drupal::request()->query->get('numbering');
    $results = self::getSearchResults($request);
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
        $result['text'] = strip_tags($result['text'], '<mark>');
        // Put the highlighted keyword into an array.
        preg_match('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], $keyword_matches);
        $bookends = preg_split('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], 2);
        preg_match('/[^ ]*$/', trim($bookends[0]), $preceding);
        $before_split = preg_split('/[^ ]*$/', trim($bookends[0]));
        $table[$inc]['start'] = $before_split[0] ?? '';
        $table[$inc]['word_before'] = $preceding[0] ?? '';
        $table[$inc]['keyword'] = $keyword_matches[0] ?? '';
        if (isset($bookends[1])) {
          preg_match('/(\s*)([^\s]*)(.*)/', $bookends[1], $following);
        }
        $table[$inc]['after'] = isset($following) ? $following[0] : '';
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
      usort($table, function ($a, $b) {
        return mb_strtolower($a['keyword']) <=> mb_strtolower($b['keyword']);
      });
      $sorted['target_asc'] = $table;
      usort($table, function ($a, $b) {
        return mb_strtolower($b['keyword']) <=> mb_strtolower($a['keyword']);
      });
      $sorted['target_desc'] = $table;

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
      $inc = 0;
      foreach ($sorted['target_asc'] as $key => $values) {
        $inc++;
        $number = $numbering == 1 ? $inc : '';
        $output['target_asc'][] = [
          $number,
          Markup::create($values['start'] . $values['word_before'] . '&nbsp;' . $values['keyword'] . $values['after']),
        ];
      }
      $inc = count($sorted['target_desc']);
      foreach ($sorted['target_desc'] as $key => $values) {
        $number = $numbering == 1 ? $inc : '';
        $output['target_desc'][] = [
          $number,
          Markup::create($values['start'] . $values['word_before'] . '&nbsp;' . $values['keyword'] . $values['after']),
        ];
        $inc--;
      }
    }
    return $output;
  }

}
