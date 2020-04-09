<?php

/**
 * @file
 * Drush commands for the corpus_search module.
 */

use Drupal\corpus_search\CorpusWordFrequency;
use Drupal\corpus_search\CorpusLemmaFrequency;

/**
 * Implements hook_drush_commands().
 */
function corpus_search_drush_command() {
  $items = [];
  $items['corpus-word-count'] = [
    'description' => "Count words",
    'aliases' => array('cwc'),
  ];
  $items['corpus-word-wipe'] = [
    'description' => "Clear the frequency analysis data",
    'aliases' => array('cww'),
  ];
  $items['corpus-lemma-count'] = [
    'description' => "Derive lemmas from word frequency",
    'aliases' => array('clc'),
  ];
  $items['corpus-lemma-wipe'] = [
    'description' => "Clear the frequency analysis data",
    'aliases' => array('clw'),
  ];
  $items['lemmatize'] = [
    'description' => "Clear the frequency analysis data",
    'arguments' => array(
      'word' => 'The word to lemmatize',
    ),
    'aliases' => array('lem'),
  ];
  return $items;
}

/**
 * Implements the drush command.
 */
function drush_corpus_search_corpus_word_count() {
  CorpusWordFrequency::analyze();
}

/**
 * Implements the drush command.
 */
function drush_corpus_search_corpus_lemma_count() {
  CorpusLemmaFrequency::analyze();
}

/**
 * Implements the drush command.
 */
function drush_corpus_search_lemmatize($word) {
  $module_handler = \Drupal::service('module_handler');
  $module_path = $module_handler->getModule('search_api_lemma')->getPath();
  $lemma = CorpusLemmaFrequency::lemmatize($word, $module_path);
  print_r($word . " => " . $lemma . PHP_EOL);
}

/**
 * Implements the drush command.
 */
function drush_corpus_search_corpus_word_wipe() {
  CorpusWordFrequency::wipe();
  print_r('Word Frequency data reset. Run drush cwc to re-run.' . PHP_EOL);
}

/**
 * Implements the drush command.
 */
function drush_corpus_search_corpus_lemma_wipe() {
  CorpusLemmaFrequency::wipe();
  print_r('Lemma Frequency data reset. Run drush clw to re-run.' . PHP_EOL);
}
