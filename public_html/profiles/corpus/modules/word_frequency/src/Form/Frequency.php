<?php

namespace Drupal\word_frequency\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\word_frequency\FrequencyService;

/**
 * Class Frequency.
 *
 * @package Drupal\word_frequency\Form
 */
class Frequency extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'word_frequency.word_frequency_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'word_frequency_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Calculate word frequency'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    FrequencyService::analyze();
  }

}
