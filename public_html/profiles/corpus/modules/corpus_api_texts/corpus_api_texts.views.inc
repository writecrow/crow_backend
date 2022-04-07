<?php

/**
 * @file
 * Register Views field handler for concordance-highlighted excerpt.
 */

/**
 * Implements hook_views_data_alter().
 */
function corpus_api_texts_views_data_alter(array &$data) {
  $data['node']['concordance_views_field'] = array(
    'title' => t('Concordance excerpt'),
    'field' => array(
      'title' => t('Concordance excerpt'),
      'help' => t('Concordance-style content highlighting'),
      'id' => 'concordance_views_field',
    ),
  );
  $data['node']['corpus_text'] = array(
    'title' => t('Full or truncated text'),
    'field' => array(
      'title' => t('Full or truncated text'),
      'help' => t('Returns full or truncated text based on parameters'),
      'id' => 'corpus_text',
    ),
  );
  $data['node']['highlighted_repository_text'] = array(
    'title' => t('Highlighted text based on query parameters'),
    'field' => array(
      'title' => t('Highlighted Repository Text'),
      'help' => t('Returns highlighted repository text'),
      'id' => 'repository_highlighted',
    ),
  );
}
