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
      $text[$name] = self::mapMetadata($text, $name, $machine_name);

      if (empty($text[$name])) {
        continue;
      }
    }
    foreach (ImporterMap::$corpusTaxonomies as $name => $machine_name) {
      if (is_array($text[$name])) {
        // Save multiple values.
        $tids = [];
        foreach ($text[$name] as $term) {
          $term_data = self::getOrCreateTidFromName($term, $machine_name, $options);
          if (!$term_data) {
            continue;
          }
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
        if (!$term_data) {
          continue;
        }
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

    // Skip N/A values.
    if (in_array($label, ImporterMap::$notAvailableValues)) {
      return FALSE;
    }

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
   *   The header, as an array, with mapped values (e.g., "SL" is now "Syllabus").
   */
  public static function mapMetadata(array $text, $name, $machine_name) {
    // Convert all string values to associative array.
    if (is_string($text[$name])) {
      $text[$name] = [$text[$name]];
    }
    foreach ($text[$name] as $key => $value) {

      switch ($machine_name) {
        case 'assignment':
          if (in_array($value, array_keys(ImporterMap::$assignments))) {
            $text[$name][$key] = ImporterMap::$assignments[$value];
          }
          break;

        case 'college':
          $institution = $text['Institution'];
          if (in_array($value, array_keys(ImporterMap::$collegeGeneral))) {
            // The text metadata uses a college acronymn.
            // The college name is shared across institutions.
            $text[$name][$key] = ImporterMap::$collegeGeneral[$value];
          }
          elseif (isset(ImporterMap::$collegeSpecific[$institution]) && in_array($value, array_keys(ImporterMap::$collegeSpecific[$institution]))) {
            // The text metadata uses a college acronymn.
            // The college name is institution-specific.
            $text[$name][$key] = ImporterMap::$collegeSpecific[$institution][$value];
          }
          break;

        case 'course':
          if (in_array($value, array_keys(ImporterMap::$courseFixes))) {
            $text[$name][$key] = ImporterMap::$courseFixes[$value];
          }
          break;

        case 'mode':
          if (in_array($value, array_keys(ImporterMap::$modeFixes))) {
            $text[$name][$key] = ImporterMap::$modeFixes[$value];
          }
          break;

        case 'country':
          if (in_array($value, array_keys(ImporterMap::$countryFixes))) {
            $text[$name][$key] = ImporterMap::$countryFixes[$value];
          }
          $text[$name][$key] = CountryCodeConverter::convert($text[$name][$key], 'name');
          break;

        case 'draft':
          if (in_array($value, array_keys(ImporterMap::$draftFixes))) {
            $text[$name][$key] = ImporterMap::$draftFixes[$value];
          }
          break;

        case 'gender':
          if (in_array($value, array_keys(ImporterMap::$genderFixes))) {
            $text[$name][$key] = ImporterMap::$genderFixes[$value];
          }
          break;

        case 'institution':
          if (in_array($value, array_keys(ImporterMap::$institutionFixes))) {
            $text[$name][$key] = ImporterMap::$institutionFixes[$value];
          }
          // Purdue texts only.
          if ($text['Institution'] == 'Purdue University' && $value == 'LR') {
            $text[$name][$key] = 'SY';
          }
          break;

        case 'program':
          if (in_array($value, array_keys(ImporterMap::$programFixes))) {
            $text[$name][$key] = ImporterMap::$programFixes[$value];
          }
          break;

        case 'semester':
          if (in_array($value, array_keys(ImporterMap::$semesters))) {
            $text[$name][$key] = ImporterMap::$semesters[$value];
          }
          break;
      }
      // Unset any N/A values.
      if (in_array($value, ImporterMap::$notAvailableValues)) {
        // Skip various N/A values. These will be reported later on.
        unset($text[$name][$key]);
      }
    }
    return $text[$name];
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
