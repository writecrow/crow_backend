<?php

namespace Drupal\corpus_api_texts\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\user\Entity\User;
use Drupal\corpus_search\Controller\CorpusSearch;
use writecrow\Highlighter\Highlighter;

/**
 * Return full or excerpted text, based on the user role.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("corpus_text")
 */
class CorpusText extends FieldPluginBase {

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
    $text_object = $entity->get('field_body')->getValue();
    $user = User::load(\Drupal::currentUser()->id());
    $text = htmlentities(strip_tags($text_object[0]['value'], "<name><date><place>"));
    $param = \Drupal::request()->query->all();
    if (isset($param['search'])) {
      $tokens = CorpusSearch::getTokens($param['search']);
      $text = Highlighter::process($text, array_keys($tokens), FALSE,  'all');
    }
    if ($user->hasRole('full_text_access')) {
      return '<div class="panel">' . nl2br($text) . '</div>';
    }
    // Default to returning a truncated version of the text.
    if (strlen($text) > 600) {
      $output = '<div class="panel"><em>This account is limited to viewing excerpts. Displaying first 600 characters. For fulltext, apply for an account by emailing <a href="mailto:collaborate@writecrow.org">collaborate@writecrow.org</a></em></div>';
      $output .= '<div class="panel">' . nl2br(preg_replace('/\s+?(\S+)?$/', '', substr($text, 0, 600)) . '...') . '</div>';
    }
    else {
      $output ='<div class="panel">' . nl2br($text) . '</div>';
    }
    return $output;
  }

}
