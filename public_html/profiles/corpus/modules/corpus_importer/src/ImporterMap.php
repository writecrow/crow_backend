<?php

namespace Drupal\corpus_importer;

/**
 * Class ImporterMap.
 *
 * @package Drupal\corpus_importer
 */
class ImporterMap {

  /**
   * {@inheritdoc}
   */
  public static $notAvailableValues = [
    'NA',
    'N/A',
    'NAN',
    '',
  ];

  /**
   * {@inheritdoc}
   */
  public static $corpusTaxonomies = [
    'Assignment' => 'assignment',
    'College' => 'college',
    'Country' => 'country',
    'Course' => 'course',
    'Draft' => 'draft',
    'Gender' => 'gender',
    'Institution' => 'institution',
    'Instructor' => 'instructor',
    'L1' => 'l1',
    'Program' => 'program',
    'Course Semester' => 'semester',
    'Course Year' => 'year',
    'Year in School' => 'year_in_school',
  ];

  /**
   * {@inheritdoc}
   */
  public static $repositoryTaxonomies = [
    'Assignment' => 'assignment',
    'Course' => 'course',
    'Mode' => 'mode',
    'Length' => 'course_length',
    'Institution' => 'institution',
    'Instructor' => 'instructor',
    'Document Type' => 'document_type',
    'Course Semester' => 'semester',
    'Course Year' => 'year',
    'File Type' => 'file_type',
    'Topic' => 'topic',
  ];

  /**
   * {@inheritdoc}
   */
  public static $assignments = [
    "AB" => "Annotated Bibliography",
    "AD" => "Digital Autobiography",
    "AR" => "Argumentative Paper",
    "AN" => "Analytical Essay",
    "BE" => "Belief Exploration",
    "CA" => "Controversy Analysis",
    "CS" => "Case Study",
    "DA" => "Visual Design Analysis",
    "DE" => "Description and Explanation",
    "EM" => "Email",
    "FA" => "Film Analysis",
    "GA" => "Genre Analysis",
    "GR" => "Genre Redesign",
    "IR" => "Interview Report",
    "IN" => "Informative Essay",
    "LN" => "Literacy Narrative",
    "LR" => "Literature Review",
    "ME" => "Memo",
    "NR" => "Narrative",
    "OL" => "Open Letter",
    "PA" => "Public Argument",
    "PO" => "Reflection",
    "PR" => "Profile",
    "PS" => "Position Argument",
    "RA" => "Rhetorical Analysis",
    "RB" => "Researcher Beliefs",
    "RE" => "Response",
    "RF" => "Reflection",
    "RR" => "Register Rewrite",
    "RP" => "Research Proposal",
    "RT" => "Research Report",
    "SA" => "Short Argument",
    "SG" => "Synthesized Genre Analysis",
    "SR" => "Summary and Response",
    "SU" => "Summary",
    "SY" => "Synthesis",
    "TA" => "Text Analysis",
    "VA" => "Variation Analysis",
  ];

  /**
   * {@inheritdoc}
   */
  public static $docTypes = [
    "AC" => "Activity",
    "SL" => "Syllabus",
    "SY" => "Syllabus",
    "LP" => "Lesson Plan",
    "AS" => "Assignment Sheet/Prompt",
    "RU" => "Rubric",
    "PF" => "Peer Review Form",
    "QZ" => "Quiz",
    "HO" => "Handout",
    "SM" => "Supporting Material",
    "SP" => "Sample Paper",
    "HD" => "Handout",
    "NA" => "Not specific to any major assignment",
  ];

  /**
   * {@inheritdoc}
   */
  public static $countryFixes = [
    'NA' => '',
    'CHI' => 'CHN',
    'MLY' => 'MYS',
    'LEB' => 'LBN',
    'TKY' => 'TUR',
    'BRZ' => 'BRA',
    'SDA' => 'SAU',
    'Iran (Islamic Republic Of)' => 'Iran',
    'Korea, Republic of' => 'South Korea',
    'Korea (South)' => 'South Korea',
    'Taiwan, Province of China' => 'Taiwan',
    'United States' => 'United States of America',
    'Viet Nam' => 'Vietnam',
  ];

  /**
   * {@inheritdoc}
   */
  public static $institutionFixes = [
    '' => 'Purdue University',
    'University of Arizona - cues' => 'University of Arizona',
  ];

  /**
   * {@inheritdoc}
   */
  public static $modeFixes = [
    'Live Online' => 'Synchronous Online',
  ];

  /**
   * {@inheritdoc}
   */
  public static $draftFixes = [
    'D1' => '1',
    'D2' => '2',
    'D3' => '3',
    'D4' => '4',
    'DF' => 'F',
  ];

  /**
   * {@inheritdoc}
   */
  public static $courseFixes = [
    '106' => 'ENGL 106',
    '107' => 'ENGL 107',
    '108' => 'ENGL 108',
    'ENGL 106i' => 'ENGL 106INTL',
  ];

  /**
   * {@inheritdoc}
   */
  public static $programFixes = [
    'Visiting Student' => '',
    'Statistics and...' => 'Statistics and Data Science',
  ];

  /**
   * {@inheritdoc}
   */
  public static $semesters = [
    'F' => 'Fall',
  ];

  /**
   * {@inheritdoc}
   */
  public static $genderFixes = [
    'G' => 'M',
  ];

  /**
   * {@inheritdoc}
   */
  public static $collegeGeneral = [
    'EU' => 'College of Education',
    'A' => 'College of Agriculture & Life Sciences',
    'HH' => 'College of Health & Human Sciences',
    'LA' => 'College of Liberal Arts',
    'PC' => 'College of Pharmacy',
    'S' => 'College of Science',
    'PI' => 'Polytechnic Institute',
    'T' => 'Polytechnic Institute',
    'PP' => 'Pre-Pharmacy',
    'CH' => 'School of Chemical Engineering',
    'EC' => 'School of Electrical & Computer Engineering',
    'ME' => 'School of Mechanical Engineering',
    'CFA' => 'College of Fine Arts',
    'SBS' => 'College of Social & Behavioral Sciences',
    'COH' => 'College of Humanities',
    'NUR' => 'College of Nursing',
    'APL' => 'College of Architecture, Planning & Landscape',
    'MED' => 'College of Medicine',
    'Colleges Letters Arts Science' => 'College of Letters Arts & Sciences',
    'College of Arch, Plan & Lands' => 'College of Architecture, Planning & Landscape',
    'College of Architecture, Planning, & Landscape' => 'College of Architecture, Planning & Landscape',
    'College of Ag & Life Sciences' => 'College of Agriculture',
    'College of Soc & Behav Sci' => 'College of Social & Behavioral Sciences',
    'School of Elec & Computer Engr' => 'School of Electrical & Computer Engineering',
    'Undergrad Non-Degree Seeking' => '',
  ];

  /**
   * {@inheritdoc}
   */
  public static $collegeSpecific = [
    'Purdue University' => [
      'US' => 'Exploratory Studies',
      'E' => 'First Year Engineering',
      'M' => 'School of Management',
    ],
    'University of Arizona' => [
      'US' => 'Colleges Letters Arts Science',
      'E' => 'College of Engineering',
      'M' => 'Eller College of Management',
      'Zuckerman Coll Public Health' => 'Zuckerman Coll Public Health',
    ],
  ];

}
