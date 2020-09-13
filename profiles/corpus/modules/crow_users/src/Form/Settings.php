<?php

namespace Drupal\crow_users\Form;

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
  const SETTINGS = 'crow_users.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'crow_users_settings';
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
    $form['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp Project ID for todo list'),
      '#default_value' => $config->get('project'),
    ];
    $form['list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp todo list ID'),
      '#default_value' => $config->get('list'),
    ];
    $form['assignee_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp User IDs to assign'),
      '#description' => $this->t('Comma-separated list'),
      '#default_value' => $config->get('assignee_ids'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('assignee_ids', $form_state->getValue('assignee_ids'))
      ->set('list', $form_state->getValue('list'))
      ->set('project', $form_state->getValue('project'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
