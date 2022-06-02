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
    $rows['last_year'] = [];
    $rows['total'] = 0;
    $rows['individual'] = 0;
    $rows['institutional'] = 0;
    $rows['full_text_access'] = 0;
    $rows['offline_access'] = 0;
    $rows['pending'] = [];

    $users = $userStorage->loadMultiple($uids);
    foreach ($users as $user) {
      $name = $user->getAccountName();
      if (!$name) {
        continue;
      }
      $rows['total']++;
      $accessed = $user->getLastAccessedTime();
      if ($accessed > (time() - 604800)) {
        $rows['last_week'][] = $name;
      }
      elseif ($accessed > (time() - (604800 * 4))) {
        $rows['last_month'][] = $name;
      }
      if ($accessed > (time() - (604800 * 52))) {
        $rows['last_year'][] = $name;
      }
      $created = $user->getCreatedTime();
      if ($created > (time() - (604800 * 4)) && $user->isActive()) {
        $rows['joined_this_month'][] = $name;
      }
      if ($created > (time() - (604800 * 8)) && $created < (time() - (604800 * 4)) && $user->isActive()) {
        $rows['joined_last_month'][] = $name;
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
      if (!$user->isActive() && $created > (time() - (604800 * 4))) {
        $rows['pending'][] = $name;
      }
    }
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', "account_insitution_organization");
    $institutions = $query->execute();

    $markup['table'] = [
      '#type' => 'table',
      '#attributes' => ['class' => [''], 'border' => '1', 'style' => 'border-spacing:0px;text-align: left;'],
      '#header' => ['Metric', 'Count', 'Details'],
      '#rows' => [
        ['Institutions served', count($institutions), ''],
        ['Total accounts', $rows['total'] - count($rows['pending']), ''],
        [
          'Pending account requests*',
          count($rows['pending']),
          implode(', ', $rows['pending']),
        ],
        [
          'Joined in the last 4 weeks',
          count($rows['joined_this_month']) . ' (Previous month: ' . count($rows['joined_last_month']) . ')',
          implode(', ', $rows['joined_this_month']),
        ],
        [
          'Active in the last week',
          count($rows['last_week']),
          implode(', ', $rows['last_week']),
        ],
        [
          'Active in the last 4 weeks',
          count($rows['last_month']) + count($rows['last_week']),
          implode(', ', $rows['last_month']),
        ],
        [
          'Active in the last year',
          count($rows['last_year']),
          '',
        ],
        ['Institutional accounts', $rows['institutional'], ''],
        ['Individual accounts', $rows['individual'], ''],
        ['Accounts with full text access', $rows['full_text_access'], ''],
        ['Accounts with offline access', $rows['offline_access'], ''],
      ],
    ];
    $markup['footnotes'] = [
      '#markup' => '*: Pending accounts display only those created in the last month.',
    ];
    return $markup;
  }

}
