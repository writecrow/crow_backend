<?php

namespace Drupal\corpus_api_texts\Plugin\views\field;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\corpus_api_texts\Kwic;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("concordance_views_field")
 */
class ConcordanceViewsField extends FieldPluginBase {

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
    $param = \Drupal::request()->query->all();
    $entity = $values->_entity;
    $text_object = $entity->get('field_body')->getValue();
    $text = $text_object[0]['value'];
    $output = '';
    if (isset($param['search'])) {
      if (isset($param['method']) && $param['method'] == 'lemma') {
        $output .= "(Showing lemmatized matches)";
        $all_words = preg_split("/[^\w]+/", $param['search'], 0, PREG_SPLIT_NO_EMPTY);
        $keywords = $this->getLemmas($param['search']);
        $param['search'] = implode(' ', array_keys($keywords));
      }
      preg_match_all("/\"([^\"]+)\"/u", $param['search'], $phrases);
      $excerpt = Kwic::excerpt($text, $param['search']);
      if (!empty($excerpt)) {
        $output .= '<table>';
        foreach ($excerpt as $line) {
          $output .= '<tr><td>' . $line . '</td></tr>';
        }
        $output .= '</table>';
        return $output;
      }
    }
    return '';
  }

  /**
   * Extracts the positive keywords used in a search query.
   *
   * @param string $string
   *   A search string.
   *
   * @return string[]
   *   An array of all unique positive keywords used in the query.
   */
  protected function getLemmas($string) {
    $module_handler = \Drupal::service('module_handler');
    $this->module_path = $module_handler->getModule('search_api_lemma')->getPath();
    $split = '/[' . Unicode::PREG_CLASS_WORD_BOUNDARY . ']+/iu';
    $keywords_in = preg_split($split, $string);
    // Assure there are no duplicates. (This is actually faster than
    // array_unique() by a factor of 3 to 4.)
    // Remove quotes from keywords.
    $lemmatized = [];
    foreach (array_filter($keywords_in) as $word) {
      $alpha = $word[0];
      $path = DRUPAL_ROOT . '/' . $this->module_path . '/data/lemmas_' . $alpha . '.php';
      if (file_exists($path)) {
        require $path;
      }
      if (isset($lemma_map[$word])) {
        $lemma = $lemma_map[$word];
      }
      else {
        $lemma = $word;
      }
      $lemmatized[$lemma] = $lemma;
    }

    $keywords = [];
    foreach (array_filter($lemmatized) as $keyword) {
      if ($keyword = trim($keyword, "'\"")) {
        $keywords[$keyword] = $keyword;
      }
      $alpha = $keyword[0];
      $path = DRUPAL_ROOT . '/' . $this->module_path . '/data/roots_' . $alpha . '.php';
      if (file_exists($path)) {
        require $path;
      }
      if (isset($root_map[$keyword])) {
        $lemmas = explode(',', $root_map[$keyword]);
        foreach ($lemmas as $lemma) {
          $keywords[$lemma] = $lemma;
        }
      }
    }

    return $keywords;
  }

}
