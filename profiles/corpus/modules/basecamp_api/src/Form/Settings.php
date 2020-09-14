<?php

namespace Drupal\basecamp_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\basecamp_api\Basecamp;

/**
 * Configure Basecamp API settings for this site.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'basecamp_api_settings';
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
    $token = \Drupal::state()->get('basecamp_api_access_token');
    if (isset($token)) {
      if (Basecamp::getPeople()) {
        \Drupal::messenger()->addStatus(t('Basecamp connection is working correctly.'));
      }
      else {
        \Drupal::messenger()->addError(t('The Basecamp connection is not working. Check the logs.'));
      }
    }
    $form['basecamp_api_user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account ID'),
      '#description' => $this->t('This numeric value can be found in the URL when you log into the Basecamp instance (e.g., https://3.basecamp.com/3129499/'),
      '#default_value' => \Drupal::state()->get('basecamp_api_user_id'),
    ];
    $form['basecamp_api_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('Created & stored at <a href=":integrations">:integrations</a>.', [
        ':integrations' => 'https://launchpad.37signals.com/integrations',
      ]),
      '#default_value' => \Drupal::state()->get('basecamp_api_client_id'),
    ];

    $form['basecamp_api_client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => \Drupal::state()->get('basecamp_api_client_secret'),
    ];

    $form['basecamp_api_redirect_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URI'),
      '#description' => $this->t('For initial handshake, this value must be set to :redirect_uri', [
        ':redirect_uri' => \Drupal::request()->getSchemeAndHttpHost() . '/basecamp_api/redirect',
      ]),
      '#default_value' => \Drupal::state()->get('basecamp_api_redirect_uri'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = [
      'basecamp_api_client_id',
      'basecamp_api_client_secret',
      'basecamp_api_user_id',
      'basecamp_api_redirect_uri',
    ];
    foreach ($settings as $key) {
      \Drupal::state()->set($key, $form_state->getValue($key));
    }
    parent::submitForm($form, $form_state);
  }

}
