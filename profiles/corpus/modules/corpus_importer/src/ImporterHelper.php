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
   *   The taxonomy fields for a corpus node with TIDs,
   *   along with any germane output messages.
   */
  public static function retrieveTaxonomyIds(array $text, array $options) {
    $fields = [];
    $messages = [];
    // Loop through each of the taxonomies defined in the backend.
    foreach (ImporterMap::$corpusTaxonomies as $name => $machine_name) {

      // Perform data mapping & cleanup operations on metadata.
      $text = self::mapMetadata($text, $name, $machine_name);

      if (!in_array($name, array_keys($text))) {
        // Skip headers that aren't known to the backend.
        $messages[] = 'Unknown header: ' . $name;
        continue;
      }
      if (in_array($text[$name], ImporterMap::$notAvailableValues)) {
        // Skip various N/A values. These will be reported later on.
        continue;
      }
      if (empty($text[$name])) {
        continue;
      }

      if (is_array($text[$name])) {
        // Save multiple values.
        $tids = [];
        foreach ($text[$name] as $term) {
          $term_data = self::getOrCreateTidFromName($term, $machine_name, $options);
          $tids[] = $term_data['tid'];
          // Report any terms newly created.
          if (!empty($term_data['message'])) {
            $messages[] = $term_data['message'];
          }
        }
        $fields[$machine_name] = $tids;
      }
      else {
        // Save a single value.
        $term_data = self::getOrCreateTidFromName($text[$name], $machine_name, $options);
        $fields[$machine_name] = $term_data['tid'];
        // Report any terms newly created.
        if (!empty($term_data['message'])) {
          $messages[] = $term_data['message'];
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
   * Given a taxonomy name and a vocabulary, retrieve the ID or create.
   *
   * @param string $label.
   *    The human-readable name, provided by the original text.
   * @param string $vocabulary.
   *    The machine-name of the backend vocabulary.
   *
   * @return int
   *   The term ID.
   */
  public static function getOrCreateTidFromName($label, $vocabulary, $options) {
    $output = [];
    $output['tid'] = self::getTidByName($label, $vocabulary);
    if ($output['tid'] == 0) {
      $output['message'] = 'New ' . $vocabulary . ' created: ' . $label;
      if (!$options['dryrun']) {
        self::createTerm($label, $vocabulary);
      }
      $output['tid'] = self::getTidByName($label, $vocabulary);
    } 
    return $output;
  }

  /**
   * Map acronyms or outdated terms to standard usage.
   *
   * @param array $text
   *   The tag-converted text array.
   * @param string $name
   *   The original name of the header (e.g., 'Course Semester').
   * @param string $machine_name
   *   The mapped machine name to the backend taxonomy (e.g., 'semester').
   *
   * @return array
   *   The headers, as an array, with mapped values (e.g., "SL" is now "Syllabus").
   */
  public static function mapMetadata(array $text, $name, $machine_name) {
    if (in_array($machine_name, ['program', 'college'])) {
      if (is_string($text[$name])) {
        if ($text[$name] == "NA") {
          // Leave "NA" program/college values as-is.
          return $text;
        }
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
    // Purdue texts
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
    // Standardize semesters.
    if ($machine_name == 'semester') {
      if (in_array($text[$name], array_keys(ImporterMap::$semesters))) {
        $code = $text[$name];
        $text[$name] = ImporterMap::$semesters[$code];
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
          // The text metadata uses a college acronymn.
          // The college name is shared across institutions.
          $text['College'][] = ImporterMap::$collegeGeneral[$college];
        }
        elseif (in_array($college, array_values(ImporterMap::$collegeGeneral))) {
          // The text metadata provides the actual college name.
          // The college name is shared across institutions.
          $text['College'][] = $college;
        }
        elseif (isset(ImporterMap::$collegeSpecific[$instititution]) && in_array($college, array_keys(ImporterMap::$collegeSpecific[$instititution]))) {
          // The text metadata uses a college acronymn.
          // The college name is institution-specific.
          $text['College'][] = ImporterMap::$collegeSpecific[$instititution][$college];
        }
        elseif (isset(ImporterMap::$collegeSpecific[$instititution]) && in_array($college, array_values(ImporterMap::$collegeSpecific[$instititution]))) {
          // The text metadata provides the actual college name.
          // The college name is institution-specific.
          $text['College'][] = $college;
        }
        else {
          $text['College'][] = $college;
        }
      }
    }
    return $text;
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
    // Default to 'Purdue University' if no institution specified.
    if (empty($text['Institution'])) {
      $text['Institution'] = 'Purdue University';
    }
    // Legacy texts use the header "Semester writing." Change this.
    if (!isset($text['Course Semester'])) {
      if (isset($text['Semester writing'])) {
        $text['Course Semester'] = $text['Semester writing'];
      }
    }
    // Legacy texts use the header "Year writing." Change this.
    if (!isset($text['Course Year'])) {
      if (isset($text['Year writing'])) {
        $text['Course Year'] = $text['Year writing'];
      }
    }
    foreach (ImporterMap::$corpusTaxonomies as $key => $value) {
      if (!in_array($key, array_keys($text)) && $key != "Instructor") {
        echo 'This text is missing the header ' . $key;
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
    if (empty($name) || empty($vid)) {
      return 0;
    }
    $properties = [
      'name' => $name,
      'vid' => $vid,
    ];
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
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
    $controller = \Drupal::service('entity_type.manager')
      ->getStorage('taxonomy_term');
    $entities = $controller->loadMultiple($tids);
    $controller->delete($entities);
  }

}
