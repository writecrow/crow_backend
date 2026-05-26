<?php

namespace Drupal\corpus_search;

use Caxy\HtmlDiff\HtmlDiff;

/**
 * Class Excerpt.
 *
 * @package Drupal\corpus_search
 */
class Diff {

  /**
   * Helper function.
   *
   * @param string $before
   *   The ID of the "before" text.
   * @param string $after
   *   The ID of the "after" text.
   *
   * @return string
   *   The diff as HTML.
   */
  public static function getDiff($before, $after, $format) {
    $map = [
      'side-by-side' => 'SideBySide',
      'combined' => 'Combined',
    ];
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n')
      ->fields('n', ['filename', 'text'])
      ->condition('n.filename', [$before, $after], 'IN');
    $query->range(0, 2);
    $results = $query->execute()->fetchAllKeyed();
    if (!isset($results[$before]) || !isset($results[$after])) {
      return '';
    }
    $beforetext = self::normalizeText($results[$before]);
    $aftertext = self::normalizeText($results[$after]);
    if (!isset($format)) {
      $result = '<div class="caxy-diff"><div class="before">' . $beforetext . '</div><div class="after">' . $aftertext . '</div></div>';
    }
    else {
      $htmlDiff = new HtmlDiff($beforetext, $aftertext);
      $result = $htmlDiff->build(); 
      if ($format === 'side-by-side') {
        $newdiff = self::stripTagsContent($result, '<del>', TRUE);
        $olddiff = self::stripTagsContent($result, '<ins>', TRUE);
        $result = '<div class="caxy-diff"><div class="before">' . $olddiff . '</div><div class="after">' . $newdiff . '</div></div>';
      }
    }
    return $result;
  }

  public static function normalizeText($string) {
    $text = trim($string);
    $text = str_replace(['<', '>'], ['[', ']'], $text);
    return nl2br($text);
  }
  
  public static function stripTagsContent($text, $tags = '', $invert = FALSE) {
    preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
    $tags = array_unique($tags[1]);
  
    if (is_array($tags) and count($tags) > 0) {
      if ($invert == FALSE) {
        return preg_replace('@<(?!(?:' . implode('|', $tags) . ')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
      }
      else {
        return preg_replace('@<(' . implode('|', $tags) . ')\b.*?>.*?</\1>@si', '', $text);
      }
    }
    elseif ($invert == FALSE) {
      return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
    }
    return $text;
  }

}
