<?php

namespace Drupal\corpus_importer;

use Drupal\taxonomy\Entity\Term;
use writecrow\CountryCodeConverter\CountryCodeConverter;

/**
 * Class ImporterHelper.
 *
 * @package Drupal\corpus_importer
 */
class ImporterHelper {

  /**
   * Ensure that a taxonomy term exists from the given data.
   *
   * @param array $text
   *   The tag-converted text with headers.
   * @param array $options
   *   Processing options, like dryrun, lorem.
   *
   * @return array
   *   The taxonomy fields for a corpus node with TIDs.
   */
  public static function retrieveTaxonomyIds(array $text, array $options) {
    $fields = [];
    $messages = [];
    foreach (ImporterMap::$corpusTaxonomies as $name => $machine_name) {
      $tid = '';
      $save = TRUE;
      if (in_array($name, array_keys($text))) {
        // Skip N/A values.
        if (in_array($text[$name], ['NA', 'N/A'])) {
          $save = FALSE;
        }
        if (in_array($machine_name, ['program', 'college'])) {
          if (is_string($text[$name])) {
            $multiples = preg_split("/\s?;\s?/", $text[$name]);
            if (isset($multiples[1])) {
              array_push($multiples, $text[$name]);
              $text[$name] = $multiples;
            }
            else {
              $text[$name] = [$text[$name]];
            }
          }
        }
        if (in_array($machine_name, ['institution']) && empty($text['Institution'])) {
          $text['Institution'] = 'Purdue University';
        }
        if (in_array($machine_name, ['gender']) && $text['Gender'] == 'G') {
          $text['Gender'] = 'M';
        }
        if ($text['Institution'] == 'Purdue University' && $text['Assignment'] == 'LR') {
          $text['Assignment'] = 'SY';
        }
        if ($machine_name == 'assignment') {
          $assignment_code = $text['Assignment'];
          $text['Assignment'] = ImporterMap::$assignments[$assignment_code];
        }
        // Standardize draft names.
        if ($machine_name == 'draft') {
          if (in_array($text[$name], array_keys(ImporterMap::$draftFixes))) {
            $code = $text[$name];
            $text[$name] = ImporterMap::$draftFixes[$code];
          }
        }
        // Standardize course names.
        if ($machine_name == 'course') {
          if (in_array($text[$name], array_keys(ImporterMap::$courseFixes))) {
            $code = $text[$name];
            $text[$name] = ImporterMap::$courseFixes[$code];
          }
        }
        // Convert country IDs to readable names.
        if ($machine_name == 'country') {
          if (in_array($text[$name], array_keys(ImporterMap::$countryFixes))) {
            $code = $text[$name];
            $text[$name] = ImporterMap::$countryFixes[$code];
          }
          $text[$name] = CountryCodeConverter::convert($text[$name], 'name');
        }
        if ($machine_name == 'college') {
          $instititution = $text['Institution'];
          $colleges = $text['College'];
          $text['College'] = [];
          foreach ($colleges as $college) {

            if (in_array($college, array_keys(ImporterMap::$collegeGeneral))) {
              $text['College'][] = ImporterMap::$collegeGeneral[$college];
            }
            elseif (in_array($college, array_values(ImporterMap::$collegeGeneral))) {
              $text['College'][] = $college;
            }
            elseif (in_array($college, array_keys(ImporterMap::$collegeSpecific[$instititution]))) {
              $text['College'][] = ImporterMap::$collegeSpecific[$instititution][$college];
            }
            elseif (in_array($college, array_values(ImporterMap::$collegeSpecific[$instititution]))) {
              $text['College'][] = $college;
            }
            else {
              // In this scenario, the college name would be a
              // human-readable format.
              $messages[] = 'College "' . $college . '" not found in importer map. Importing as-is.';
              $text['College'][] = $college;
            }
          }
        }
        if ($save) {
          if (is_array($text[$name])) {
            $tids = [];
            foreach ($text[$name] as $term) {
              $tid = self::getTidByName($term, $machine_name);
              if ($tid == 0 && $options['dryrun'] === FALSE) {
                self::createTerm($term, $machine_name);
                $tid = self::getTidByName($term, $machine_name);
              }
              $tids[] = $tid;
            }
            $fields[$machine_name] = $tids;
          }
          else {
            $tid = self::getTidByName($text[$name], $machine_name);
            if ($tid == 0 && isset($text[$name]) && $options['dryrun'] === FALSE) {
              self::createTerm($text[$name], $machine_name);
              $tid = self::getTidByName($text[$name], $machine_name);
            }
            if ($tid != 0) {
              $fields[$machine_name] = $tid;
            }
          }
        }
      }
    }
    $output = ['fields' => $fields];
    if (!empty($messages)) {
      $output['messages'] = $messages;
    }
    return $output;
  }

  /**
   * Fix inconsistencies/legacy headers.
   *
   * @param array $text
   *   The tag-converted text array.
   *
   * @return array
   *   The fixed array.
   */
  public static function validateCorpusText(array $text) {
    // Default to 'Purdue University' if no insitution specified.
    if (empty($text['Institution'])) {
      $text['Institution'] = 'Purdue University';
    }
    if (!isset($text['Course Semester'])) {
      if (isset($text['Semester writing'])) {
        $text['Course Semester'] = $text['Semester writing'];
      }
    }
    if (!isset($text['Course Year'])) {
      if (isset($text['Year writing'])) {
        $text['Course Year'] = $text['Year writing'];
      }
    }
    foreach (ImporterMap::$corpusTaxonomies as $key => $value) {
      if (!in_array($key, array_keys($text)) && $key != "Instructor") {
        print_r($text);
        echo 'This text is missing the header ' . $key;
        die();
      }
    }
    return $text;
  }

  /**
   * Utility: find term by name and vid.
   *
   * @param string $name
   *   Term name.
   * @param string $vid
   *   Term vid.
   *
   * @return int
   *   Term id or 0 if none.
   */
  public static function getTidByName($name = NULL, $vid = NULL) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);

    return !empty($term) ? $term->id() : 0;
  }

  /**
   * Helper function.
   */
  public static function createTerm($name, $taxonomy_type) {
    Term::create([
      'name' => $name,
      'vid' => $taxonomy_type,
    ])->save();
    return TRUE;
  }

  /**
   * Delete all taxonomy terms from a vocabulary.
   *
   * @param string $vid
   *   The vocabulary id.
   */
  public static function taxonomyWipe($vid) {
    $tids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vid)
      ->execute();
    if (empty($tids)) {
      return;
    }
    $controller = \Drupal::entityManager()
      ->getStorage('taxonomy_term');
    $entities = $controller->loadMultiple($tids);
    $controller->delete($entities);
  }

}
