<?php

namespace Drupal\corpus_search;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Options\DifferOptions;
use Jfcherng\Diff\Options\RendererOptions;
use Jfcherng\Diff\Renderer\RendererConstant;

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
  public static function getDiff($before, $after) {
    $connection = \Drupal::database();
    $query = $connection->select('corpus_texts', 'n')
      ->fields('n', ['filename', 'text'])
      ->condition('n.filename', [$before, $after], 'IN');
    $query->range(0, 2);
    $results = $query->execute()->fetchAllKeyed();
    if (!isset($results[$before]) || !isset($results[$after])) {
      return '';
    }
    // renderer class name:
    //     Text renderers: Context, JsonText, Unified
    //     HTML renderers: Combined, Inline, JsonHtml, SideBySide
    $rendererName = 'Combined';
    $differOptions = new DifferOptions(
      // show how many neighbor lines; Differ::CONTEXT_ALL shows the whole file
      context: 3,
      // ignore case difference
      ignoreCase: false,
      // ignore line ending difference
      ignoreLineEnding: false,
      // ignore whitespace difference
      ignoreWhitespace: false,
      // if the input sequence is too long, give up (especially for char-level diff)
      lengthLimit: 2000,
      // when inputs are identical, render the whole content rather than an empty result
      fullContextIfIdentical: false,
    );
    // the renderer options
    $rendererOptions = new RendererOptions(
      // how detailed the rendered HTML in-line diff is? (none, line, word, char)
      detailLevel: 'word',
      // renderer language: eng, cht, chs, jpn, ...
      // or an array which has the same keys with a language file
      // check the "Custom Language" section in the readme for more advanced usage
      language: 'eng',
      // show line numbers in HTML renderers
      lineNumbers: FALSE,
      // show a separator between different diff hunks in HTML renderers
      separateBlock: FALSE,
      // show the (table) header
      showHeader: FALSE,
      // render spaces/tabs as <span class="ch sp"> </span> tags (visualised via CSS)
      spaceToHtmlTag: TRUE,
      // convert consecutive spaces to &nbsp; in HTML output
      spacesToNbsp: FALSE,
      // HTML renderer tab width (negative = do not convert into spaces)
      tabSize: 4,
      // Combined renderer: merge replace-blocks whose changed ratio is at or below this threshold (0–1)
      mergeThreshold: 0.8,
      // Unified/Context renderers CLI colorization:
      // RendererConstant::CLI_COLOR_AUTO   = colorize if possible (default)
      // RendererConstant::CLI_COLOR_ENABLE = force colorize
      // RendererConstant::CLI_COLOR_DISABLE = force no color
      cliColorization: RendererConstant::CLI_COLOR_AUTO,
      // JSON renderer: emit op tags as human-readable strings instead of ints
      outputTagAsString: TRUE,
      // JSON renderer: flags passed to json_encode()
      // see https://www.php.net/manual/en/function.json-encode.php
      jsonEncodeFlags: \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
      // word-level diff: adjacent segments joined by these characters are merged into one
      // e.g. "<del>good</del>-<del>looking</del>" → "<del>good-looking</del>"
      wordGlues: ['-', ' '],
      // return this string verbatim when the two inputs are identical; null = renderer default
      resultForIdenticals: null,
      // extra CSS classes added to the diff container <div> in HTML renderers
      wrapperClasses: ['diff-wrapper'],
    );
    $result = DiffHelper::calculate($results[$before], $results[$after], $rendererName, $differOptions, $rendererOptions);
    return $result;
  }

}
