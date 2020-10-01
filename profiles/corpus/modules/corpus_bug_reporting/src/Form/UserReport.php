<?php

namespace Drupal\corpus_bug_reporting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

class UserReport extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'corpus_bug_reporting_user_report';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $url = \Drupal::request()->query->get('url');

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Problem title'),
      '#description' => $this->t('Give a brief title to the type of issue you are encountering'),
      '#required' => TRUE,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web URL where the problem exists'),
      '#default_value' => UrlHelper::filterBadProtocol($url) ?? '',
    ];
    $form['user'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Username'),
      '#default_value' => $user->getUsername(),
    ];
    $form['timestamp'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Timestamp'),
      '#default_value' => date('F j, Y g:ia', time()),
    ];
    $form['platform'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Platform details'),
      '#default_value' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    ];
    $roles = $user->getRoles();
    $reported_roles = array_diff($roles, ['authenticated', 'administrator']);
    $form['roles'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Roles'),
      '#default_value' => $reported_roles,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Describe the problem'),
      '#description' => $this->t('If this problem requires multiple steps to trigger, please provide step-by-step actions to reproduce the problem.'),
      '#required' => TRUE,
    ];

    $validators = [
      'file_validate_extensions' => ['png', 'jpg', 'jpeg'],
    ];
    $form['screenshot'] = [
      '#type' => 'managed_file',
      '#name' => 'screenshot',
      '#title' => t('Optionally, add a screenshot of the problem, if appropriate.'),
      '#size' => 20,
      '#description' => t('Image file formats allowe: .png, .jpg, .jpeg'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://bug_reports/',
    ];

    $form['contact'] = [
      '#type' => 'checkbox',
      '#default_value' => TRUE,
      '#title' => $this->t('Contact me with updates about this issue.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit report'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $config = \Drupal::config('corpus_bug_reporting.settings');
    if (!$config->get('on')) {
      return;
    }
    if ($file = \Drupal::entityTypeManager()->getStorage('file')->load($form_state->getValue('screenshot')[0])) {
      $host = \Drupal::request()->getSchemeAndHttpHost();
      $url = $file->createFileUrl();
      $image_url = $host . $url;
    }

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'corpus_bug_reporting';
    $key = 'corpus_bug_reporting';
    $to = $config->get('notification_email');
    $params['message'] = 'DESCRIPTION: ' .  Html::escape($form_state->getValue('description')) . PHP_EOL . PHP_EOL;
    if ($image_url) {
      $params['message'] .= 'SCREENSHOT: ' . $image_url . PHP_EOL . PHP_EOL;
    }
    $params['message'] .= 'USER REPORTING: ' . $form_state->getValue('user') . PHP_EOL . PHP_EOL;
    $params['message'] .= 'USER ACCESS LEVEL: ' . $form_state->getValue('roles') . PHP_EOL . PHP_EOL;
    $params['message'] .= 'TIMESTAMP: ' . $form_state->getValue('timestamp') . PHP_EOL . PHP_EOL;
    $params['message'] .= 'PLATFORM/DEVICE: ' . $form_state->getValue('platform') . PHP_EOL . PHP_EOL;
    $params['message'] .= 'SOURCE: ' . $form_state->getValue('url') . PHP_EOL . PHP_EOL;
    if ($form_state->getValue('contact')) {
      $params['message'] .= 'NOTIFY THE REPORTER: yes (' . $user->getEmail() . ')' . PHP_EOL . PHP_EOL;
    }
    else {
      $params['message'] .= 'NOTIFY THE REPORTER: no' . PHP_EOL . PHP_EOL;
    }
    $params['title'] = Html::escape($form_state->getValue('title'));
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    $url = UrlHelper::filterBadProtocol($form_state->getValue('url'));
    $response = new TrustedRedirectResponse(Url::fromUri($url)->toString());
    $metadata = $response->getCacheableMetadata();
    $metadata->setCacheMaxAge(0);
    $form_state->setResponse($response);

  }
}
