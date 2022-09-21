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
    $form['on'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable notifications'),
      '#default_value' => $config->get('on'),
    ];
    $form['offline_survey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL to survey for offline request.'),
      '#default_value' => $config->get('offline_survey'),
    ];
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
    $form['description_standard'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Standard role description'),
      '#default_value' => $config->get('description_standard'),
    ];
    $form['description_full_text_access'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Full text role description'),
      '#default_value' => $config->get('description_full_text_access'),
    ];
    $form['description_export'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Export role description'),
      '#default_value' => $config->get('description_export'),
    ];
    $form['description_offline'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Offline role description'),
      '#default_value' => $config->get('description_offline'),
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
      ->set('assignee_ids', $form_state->getValue('assignee_ids'))
      ->set('list', $form_state->getValue('list'))
      ->set('project', $form_state->getValue('project'))
      ->set('offline_survey', $form_state->getValue('offline_survey'))
      ->set('description_standard', $form_state->getValue('description_standard'))
      ->set('description_full_text_access', $form_state->getValue('description_full_text_access'))
      ->set('description_export', $form_state->getValue('description_export'))
      ->set('description_offline', $form_state->getValue('description_offline'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
