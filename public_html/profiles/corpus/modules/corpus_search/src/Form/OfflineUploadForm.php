<?php

namespace Drupal\corpus_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Allow authorized users to upload a single zip file of the corpus.
 *
 * @package Drupal\corpus_search\Form
 */
class OfflineUploadForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'corpus_search.offline_upload_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'offline_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $fid = \Drupal::state()->get('offline_file_id');
    if (!$fid) {
      $fid = 0;
    }
    // $file = File::load($fid);
    $form['file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'private://',
      '#multiple' => FALSE,
      '#description' => $this->t('Allowed extensions: zip'),
      '#upload_validators' => [
        'file_validate_extensions' => ['zip'],
      ],
      '#default_value' => [$fid],
      '#title' => $this->t('Upload a zip file'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save uploaded file to system'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $field = $form_state->getValue('file');
    if (!$field[0]) {
      $form_state->setErrorByName('file', $this->t("Upload failed. Please try again."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getValue('file');
    $file = File::load($field[0]);
    // This will set the file status to 'permanent' automatically.
    \Drupal::service('file.usage')->add($file, 'corpus_search', 'file', $file->id());

    \Drupal::state()->set('offline_file_id', $file->id());
  }

}
