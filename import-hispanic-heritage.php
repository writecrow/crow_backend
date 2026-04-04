<?php

use Drupal\node\Entity\Node;

ini_set("memory_limit", "4096M");


$dir = "/app/corpus_data/hsi";
array_slice(scandir($dir), 2);
$files = [];
$objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
foreach ($objects as $filepath => $object) {
  if (stripos($filepath, '.txt') !== FALSE) {
    $files[] = $filepath;
  }
}
foreach ($files as $file) {
  $contents = file_get_contents($file);
  //print_r($contents);
  if (str_contains($contents, '<Heritage Spanish Speaker: Y>')) {
    $hsi[] = $file;
  }
}
foreach ($hsi as $file) {
  $title = basename($file, ".txt");
  $existing_node = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('title', $title)
    ->sort('nid', 'DESC')
    ->range(0, 1)
    ->execute();
  if (!empty($existing_node)) {
    $node = Node::load(reset($existing_node));
    $id = $node->id();
    $value = $node->get('field_l1')->getValue();
    $value[] = [
      'target_id' => 871,
    ];
    $node->set('field_l1', $value);
    $node->save();
    print_r("Processed Node $id , title $title" . PHP_EOL);
  }
}
