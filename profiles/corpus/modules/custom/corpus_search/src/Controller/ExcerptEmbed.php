<?php

namespace Drupal\corpus_search\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Request;

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
    $results = self::getSearchResults($request, "fixed");
    $response = new CacheableResponse('', 200);
    $output = "<!doctype html><html><head><script async src='https://www.googletagmanager.com/gtag/js?id=UA-130278011-1'></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'UA-130278011-1');
  </script>
  <meta charset='utf-8'>
  <title>Crow: Corpus & Repository of Writing</title>";
      $output .= "<style>
        body {
          font-size: 14px;
          font-family: 'Lucida Console', Monaco, monospace;
          width: 1200px;
        }
        table {
          white-space: pre;
          border-collapse: collapse;
          width: 100%;
          margin-top: 2rem;
        }
        tr:first-child > td:first-child { padding-bottom: 2rem; }
        tr:nth-child(even) {
          background-color: #f5f5f5;
        }
        td {
          white-space: nowrap;
          padding-top: 0.2rem;
          padding-bottom:0.2rem;
        }
        #concordance_lines {
          overflow-y: auto;
        }
        @media only screen and (max-width: 600px) {
          table {
            white-space: normal;
            font-size: 16px;
          }
          ::-webkit-scrollbar {
            width: 30px;
          }
          /* Track */
          ::-webkit-scrollbar-track {
            box-shadow: inset 0 0 5px grey; 
            border-radius: 10px;
          }
          /* Handle */
          ::-webkit-scrollbar-thumb {
            background: #3d3d3d; 
            border-radius: 10px;
          }
          /* Handle on hover */
          ::-webkit-scrollbar-thumb:hover {
            background: #b30000; 
          }
        }
      </style>";
    if (!empty($results['search_results'])) {
      $inc = 0;
      $lines = [];
      foreach ($results['search_results'] as $result) {
        if ($inc > 19) {
          break;
        }
        $inc++;
        $three = [];
        preg_match('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], $three);
        $bookends = preg_split('/<mark>([^<]*)<\/mark>(.[^\w]*)/u', $result['text'], 2);
        $start = $bookends[0];
        $end = $bookends[1];
        preg_match('/[^ ]*$/', trim($bookends[0]), $two);
        $one = preg_split('/[^ ]*$/', trim($start));
        preg_match('/(\s*)([^\s]*)(.*)/', $end, $trailing);
        $before_length = mb_strlen($one[0] . $two[0]);
        if ($before_length < 60) {
          $makeup = 60 - $before_length;
          $first = str_repeat("&nbsp;", $makeup) . $one[0];
        }
        else {
          $first = $one[0];
        }
        $second = empty($two[0]) ? ' ' : $two[0];
        $lines[] = ['<tr><td>' . $first, $second, $three[0], $trailing[2], $trailing[3] . '</td></tr>'];
      }
      $json_lines = json_encode($lines);
      $output .= '</head><body><table><tbody id="concordance_lines"></tbody></table>
        <script>
        lines = ' . $json_lines . ' 

        // comparator function to sort by word after the kwic
        function comparator_after(a, b) {
          first = a[3] === "" ? " " : a[3].toLowerCase();
          second = b[3] === "" ? " " : b[3].toLowerCase();
          if (first < second) return -1;
          if (first > second) return 1;
          return 0;
        }

        // comparator function to sort by word before the kwic
        function comparator_before(a, b) {
          first =  a[1] === "" ? " " : a[1].toLowerCase();
          second = b[1] === "" ? " " : b[1].toLowerCase();
          if (first < second) return -1;
          if (first > second) return 1;
          return 0;
        }

        // function to sort lines by the word after the kwic
        function sort_after() {
          sorted_lines = lines.sort(comparator_after);

          const conc_line_div = document.getElementById("concordance_lines");
          conc_line_div.innerHTML = "<tr><td>The lines below are sorted by the word right <strong>after</strong> the key word in context. <a href=\"#\" onclick=\"sort_before();return false;\">Sort by the word before</a>.</td></tr>";

          for (const line of sorted_lines) {
            conc_line_div.innerHTML += line.join(" ");
          }

        }

        // function to sort lines by the word before the kwic
        function sort_before() {
          sorted_lines = lines.sort(comparator_before);

          const conc_line_div = document.getElementById("concordance_lines");
          conc_line_div.innerHTML = "<tr><td>The lines below are sorted by the word right <strong>before</strong> the key word in context. <a href=\"#\" onclick=\"sort_after();return false;\">Sort by the word after</a>.</td></tr>";

          for (const line of sorted_lines) {
            conc_line_div.innerHTML += line.join(" ");
          }

        }

        // start with lines sorted by the word before the kwic
        sort_before();


        </script></body></html>';
      $response->setContent($output);
    }
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args']);
    return $response;
  }

}
