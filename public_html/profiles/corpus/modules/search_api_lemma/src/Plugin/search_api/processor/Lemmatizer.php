<?php

namespace Drupal\search_api_lemma\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Lemmatizes search terms.
 *
 * @SearchApiProcessor(
 *   id = "lemmatizer",
 *   label = @Translation("Lemmatizer"),
 *   description = @Translation("Lemmatizes search terms (for example, <em>went</em> to <em>go</em>). Currently, this only acts on English language content. Place after tokenizing processor."),
 *   stages = {
 *     "pre_index_save" = 0,
 *     "preprocess_index" = 0,
 *     "preprocess_query" = 0,
 *   }
 * )
 */
class Lemmatizer extends FieldsProcessorPluginBase {

  /**
   * Path to this module.
   *
   * @var string
   */
  protected $module_path = '';

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      // Limit this processor to English language data.
      if ($item->getLanguage() !== 'en') {
        continue;
      }
      foreach ($item->getFields() as $name => $field) {
        if ($this->testField($name, $field)) {
          $this->processField($field);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return $this->getDataTypeHelper()->isTextType($type);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $module_handler = \Drupal::service('module_handler');
    $this->module_path = $module_handler->getModule('search_api_lemma')->getPath();
    // In the absence of the tokenizer processor, this ensures split words.
    $words = preg_split('/[^\p{L}\p{N}]+/u', strip_tags($value), -1, PREG_SPLIT_NO_EMPTY);
    $stemmed = [];
    $lemmas = [];
    foreach ($words as $i => $word) {
      $alpha = $word[0];
      $path = DRUPAL_ROOT . '/' . $this->module_path . '/data/lemmas_' . $alpha . '.php';
      if (file_exists($path)) {
        require $path;
      }
      if (isset($lemma_map[$word])) {
        $lemmatized[] = $lemma_map[$word];
      }
      else {
        $lemmatized[] = $word;
      }
    }
    $value = implode(' ', $lemmatized);
  }

}
