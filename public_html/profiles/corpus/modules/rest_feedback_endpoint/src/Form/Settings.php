<?php

namespace Drupal\rest_feedback_endpoint\Form;

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
  const SETTINGS = 'rest_feedback_endpoint.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rest_feedback_endpoint_settings';
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
    $form['subject_line_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject line prefix'),
      '#description' => $this->t('Prepend the subject line of the email (e.g. "Mysite user feedback: ".'),
      '#default_value' => $config->get('subject_line_prefix'),
    ];
    $form['basecamp'] = [
      '#type' => 'details',
      '#title' => 'Basecamp integration',
      '#collapsible' => TRUE,
    ];
    $form['basecamp']['basecamp_project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp Project ID for todo list'),
      '#description' => $this->t('URL structure: https://3.basecamp.com/ORGANIZATION/projects/PROJECT'),
      '#default_value' => $config->get('basecamp_project'),
    ];
    $form['basecamp']['basecamp_list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp todo list ID'),
      '#description' => $this->t('URL structure: https://3.basecamp.com/ORGANIZATION/buckets/PROJECT/todolists/LIST'),
      '#default_value' => $config->get('basecamp_list'),
    ];
    $form['basecamp']['basecamp_assignee_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basecamp User IDs to assign'),
      '#description' => $this->t('Comma-separated list. Assignee IDs can be found in URLs at https://3.basecamp.com/ORGANIZATION/reports/todos/assigned/'),
      '#default_value' => $config->get('basecamp_assignee_ids'),
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
      ->set('subject_line_prefix', $form_state->getValue('subject_line_prefix'))
      ->set('basecamp_project', $form_state->getValue('basecamp_project'))
      ->set('basecamp_list', $form_state->getValue('basecamp_list'))
      ->set('basecamp_assignee_ids', $form_state->getValue('basecamp_assignee_ids'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
