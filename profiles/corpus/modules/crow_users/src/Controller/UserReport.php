<?php

namespace Drupal\crow_users\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Defines methods for creating the user report.
 */
class UserReport extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
    $data = self::prepareData();
    return $data;
  }

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public static function prepareData() {
    $userStorage = \Drupal::entityTypeManager()->getStorage('user');

    $query = $userStorage->getQuery();
    $uids = $query->execute();

    $rows = [];
    $rows['joined_this_month'] = [];
    $rows['last_month'] = [];
    $rows['last_week'] = [];
    $rows['total'] = 0;
    $rows['individual'] = 0;
    $rows['institutional'] = 0;
    $rows['full_text_access'] = 0;
    $rows['offline_access'] = 0;
    $users = $userStorage->loadMultiple($uids);
    foreach ($users as $user) {
      $name = $user->getAccountName();
      if (!$name) {
        continue;
      }
      $rows['total']++;
      if (!$user->isActive()) {
        $rows['pending'][] = $name;
      }
      $accessed = $user->getLastAccessedTime();
      if ($accessed > (time() - 604800)) {
        $rows['last_week'][] = $name;
      }
      elseif ($accessed > (time() - (604800 * 4))) {
        $rows['last_month'][] = $name;
      }
      $created = $user->getCreatedTime();
      if ($created > (time() - (604800 * 4))) {
        $rows['joined_this_month'][] = $name;
      }
      $type = $user->get('field_account_type')->getString();
      if ($type === 'individual') {
        $rows['individual']++;
      }
      elseif ($type === 'institutional') {
        $rows['institutional']++;
      }
      if ($user->hasRole('full_text_access')) {
        $rows['full_text_access']++;
      }
      if ($user->hasRole('offline_access')) {
        $rows['offline_access']++;
      }
    }
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', "account_insitution_organization");
    $institutions = $query->execute();

    $markup['table'] = [
      '#type' => 'table',
      '#header' => ['Metric', 'Count', 'Notes'],
      '#rows' => [
        ['Active accounts', $rows['total'] - count($rows['pending']), ''],
        [
          'Pending accounts',
          count($rows['pending']),
          implode(', ', $rows['pending']),
        ],
        ['Institutions served', count($institutions)],
        [
          'Joined in the last month',
          count($rows['joined_this_month']),
          implode(', ', $rows['joined_this_month']),
        ],
        [
          'Active in the last week',
          count($rows['last_week']),
          implode(', ', $rows['last_week']),
        ],
        [
          'Active in the last month',
          count($rows['last_month']) + count($rows['last_week']),
          implode(', ', $rows['last_month']),
        ],
        ['Institutional accounts', $rows['institutional'], ''],
        ['Individual accounts', $rows['individual'], ''],
        ['Accounts with full text access', $rows['full_text_access'], ''],
        ['Accounts with offline access', $rows['offline_access'], ''],
      ],
    ];
    return $markup;
  }

}
