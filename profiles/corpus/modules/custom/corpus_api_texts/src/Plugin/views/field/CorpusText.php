<?php

namespace Drupal\corpus_api_texts\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\user\Entity\User;

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

    if ($user->hasRole('full_text_access')) {
      return nl2br($text);
    }
    // Default to returning a truncated version of the text.
    if (strlen($text) > 600) {
      $output = '<h3>(Excerpted)</h3><p>Displaying first 600 characters. For fulltext, apply for an account by emailing <a href="mailto:collaborate@writecrow.org">collaborate@writecrow.org</a></p><hr />';
      $output .= nl2br(preg_replace('/\s+?(\S+)?$/', '', substr($text, 0, 600)) . '...');
    }
    else {
      $output = nl2br($text);
    }
    return $output;
  }

}
