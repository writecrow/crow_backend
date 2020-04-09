<?php

namespace Drupal\corpus_api_texts\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\corpus_search\CorpusWordFrequency as Frequency;
use writecrow\Highlighter\HighlightExcerpt;

/**
 * Return highlighted text, based on query parameters.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("repository_highlighted")
 */
class RepositoryHighlighted extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;

    $text_object = $entity->get('field_raw_text')->getValue();
    $text = htmlentities(strip_tags($text_object[0]['value'], "<name><date><place>"));
    $param = \Drupal::request()->query->all();
    if (isset($param['search'])) {
      $tokens = self::getTokens($param['search']);
      $text = HighlightExcerpt::highlight($text, array_keys($tokens), FALSE);
    }
    return nl2br($text);
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
        $length = strlen($token);
        if ((substr($token, 0, 1) == '"') && (substr($token, $length - 1, 1) == '"')) {
          $cleaned = substr($token, 1, $length - 2);
          if (preg_match("/[^a-zA-Z]/", $cleaned)) {
            // This is a quoted string. Do a phrasal search.
            $result[$cleaned] = 'phrase';
          }
          else {
            // This is a case-sensitive word search.
            $result[$cleaned] = 'quoted-word';
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

}
