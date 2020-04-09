<?php

namespace Drupal\corpus_importer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\corpus_importer\ImporterService;

/**
 * Class ImportForm.
 *
 * @package Drupal\corpus_importer\Form
 */
class ImportForm extends ConfigFormBase {

  /**  
   * {@inheritdoc}  
   */  
  protected function getEditableConfigNames() {  
    return [  
      'corpus_importer.corpus_import_form',  
    ];  
  }  

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'corpus_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['plupload'] = array(
      '#type' => 'plupload',
      '#title' => t('Upload files'),
      '#description' => t('The folder or files containing the texts to be imported.'),
      '#submit_element' => '#edit-submit',
      '#upload_validators' => array(
        'file_validate_extensions' => array('txt rtf'),
      ),
      '#plupload_settings' => array(
        'runtimes' => 'html5, flash, html4',
        'max_file_size' => 20000000,
        'chunk_size' => '20mb',
      ),
    );
    $form['merge'] = [
      '#type' => 'checkbox',
      '#default_value' => TRUE,
      '#title' => 'Update existing records, rather than creating duplicates',
      '#description' => 'A record is considered "existing" if the "ID" field is already associated with a record in the system.',
    ];
    $form['lorem'] = [
      '#type' => 'checkbox',
      '#default_value' => FALSE,
      '#title' => 'Replace text with lorem ipsum content.',
      '#description' => 'Useful for testing without canonical data.',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import texts'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    foreach ($form_state->getValue('plupload') as $uploaded_file) {
      if ($uploaded_file['status'] != 'done') {
        $form_state->setErrorByName('plupload', t("Upload of %filename failed.", array('%filename' => $uploaded_file['name'])));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $files = $form_state->getValue('plupload');
    $merge = $form_state->getValue('merge');
    $lorem = $form_state->getValue('lorem');
    ImporterService::import($files, array('merge' => $merge, 'lorem' => $lorem));
  }

}
