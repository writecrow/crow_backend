<?php

// phpcs:ignoreFile

/**
 * Enable local development services.
 */
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/default/development.services.yml';

/**
 * Show all error messages, with backtrace information.
 *
 * In case the error level could not be fetched from the database, as for
 * example the database connection failed, we rely only on this value.
 */
$config['system.logging']['error_level'] = 'verbose';

$lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
$databases['default']['default'] = [
  'database' => $lando_info['database']['creds']['database'],
  'username' => $lando_info['database']['creds']['user'],
  'password' => $lando_info['database']['creds']['password'],
  'host' => $lando_info['database']['internal_connection']['host'],
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['trusted_host_patterns'] = [
  '^api\.lndo\.site$',
];

$config['simple_oauth.settings']['public_key'] = "/app/public.key";
$config['simple_oauth.settings']['private_key'] = "/app/private.key";

$config['phpmailer_smtp.settings']['smtp_username'] = '';
$config['phpmailer_smtp.settings']['smtp_password'] = '';
$config['system.mail']['interface']['default'] = 'test_mail_collector';
