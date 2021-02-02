<?php

namespace Drupal\corpus_importer;

/**
 * Class DeDupeHelper.
 *
 * @package Drupal\corpus_importer
 */
class DeDupeHelper {

  /**
   * Get a list of duplicates based on node title & body.
   *
   * @return array
   *   An array of nodes that are duplicates.
   */
  public static function audit() {
    $database = \Drupal::database();
    $query = $database->query("SELECT a.nid, a.title
      FROM {node_field_data} a
      JOIN (SELECT title, COUNT(*)
        FROM {node_field_data}
        GROUP BY title
        HAVING count(*) > 1 ) b
      ON a.title = b.title");
    $result = $query->fetchAll();
    $filenames = [];
    foreach ($result as $i) {
      $filenames[$i->title][] = $i->nid;
    }
    $falsey_duplicates = [];
    // Determine whether these are "true" duplicates, based on body text.
    foreach ($filenames as $filename => $nids) {
      $prev_body = '';
      foreach ($nids as $nid) {
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->load($nid);
        if (!$node->hasField('field_body')) {
          continue;
        }
        $body = $node->get('field_body')->getValue();
        if ($prev_body !== '') {
          if ($body !== $prev_body) {
            $falsey_duplicates[] = $filename;
          }
        }
        else {
          $prev_body = $body;
        }
      }
    }
    return [
      'all_matches' => $filenames,
      'falsey duplicates' => $falsey_duplicates,
    ];

  }

}
