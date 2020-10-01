<?php

namespace Drupal\corpus_bug_reporting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure user settings for this site.
 */
class Settings extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'corpus_bug_reporting.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'corpus_bug_reporting_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form['on'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable notifications'),
      '#default_value' => $config->get('on'),
    ];
    $form['notification_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification email'),
      '#description' => $this->t('Single email only'),
      '#default_value' => $config->get('notification_email'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('on', $form_state->getValue('on'))
      ->set('notification_email', $form_state->getValue('notification_email'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
