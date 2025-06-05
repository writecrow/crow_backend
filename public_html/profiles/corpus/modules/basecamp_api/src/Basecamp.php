<?php

namespace Drupal\basecamp_api;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

/**
 * Talk to Basecamp via the v3 API.
 */
class Basecamp {

  const BASE_URI = 'https://3.basecampapi.com/';

  /**
   * https://github.com/basecamp/bc3-api/blob/master/sections/people.md
   */
  public static function getPeople() {
    $endpoint = '/circles/people.json';
    return self::request($endpoint, 'GET');
  }

  /**
   * https://github.com/basecamp/bc3-api/blob/master/sections/todos.md
   */
  public static function getTodosByList($project, $list) {
    // /buckets/1/todolists/3/todos.json will return a paginated list of active to-dos in the project with an ID of 1 and the to-do list with ID of 3.
    $endpoint = '/buckets/' . $project . '/todolists/' . $list . '/todos.json';
    return self::request($endpoint, 'GET');
  }

  /**
   * https://github.com/basecamp/bc3-api/blob/master/sections/todos.md
   */
  public static function createTodo($project, $list, $data) {
    // POST /buckets/1/todolists/3/todos.json creates a to-do in the project with ID 1 and under the to-do list with an ID of 3
    $endpoint = '/buckets/' . $project . '/todolists/' . $list . '/todos.json';
    return self::request($endpoint, 'POST', $data);
  }

  /**
   * Helper method to perform the HTTP request.
   *
   * @param string $endpoint
   *   The Basecamp URL endpoint.
   * @param string $type
   *   "GET" or "POST".
   * @param array $params
   *   Form parameters for POST requests.
   *
   * @return mixed
   *   The Basecamp response in PHP array format, or FALSE if failed.
   */
  public static function request($endpoint, $type = 'GET', $params = []) {
    $config = self::getConfig();
    if (!isset($config['user_id'])) {
      \Drupal::logger('basecamp_api')->error('Insufficient Basecamp API data. Check the settings page.');
      return FALSE;
    }
    $client = new Client(['base_uri' => self::BASE_URI]);
    $headers = [
      'Authorization' => 'Bearer ' . \Drupal::state()->get('basecamp_api_access_token'),
      'Accept' => 'application/json',
    ];

    switch ($type) {
      case 'POST':
        $options = [
          'json' => $params,
          'headers' => $headers,
        ];
        try {
          $response = $client->post($config['user_id'] . $endpoint, $options);
        }
        catch (RequestException $e) {
          if ($e->hasResponse()) {
            \Drupal::logger('basecamp_api')->error(Message::toString($e->getResponse()));
            return FALSE;
          }
        }
        break;

      default:
        try {
          $response = $client->request('GET', $config['user_id'] . $endpoint, [
            'headers' => $headers,
          ]);
        }
        catch (RequestException $e) {
          if ($e->hasResponse()) {
            \Drupal::logger('basecamp_api')->error(Message::toString($e->getResponse()));
            return FALSE;
          }
        }

        break;
    }

    return json_decode($response->getBody(), TRUE);
  }

  private static function getConfig() {
    $config = [
      'client_id' => \Drupal::state()->get('basecamp_api_client_id'),
      'client_secret' => \Drupal::state()->get('basecamp_api_client_secret'),
      'user_id' => \Drupal::state()->get('basecamp_api_user_id'),
      'redirect_uri' => \Drupal::state()->get('basecamp_api_redirect_uri'),
      'access_token' => \Drupal::state()->get('basecamp_api_access_token'),
      'refresh_token' => \Drupal::state()->get('basecamp_api_refresh_token'),
    ];
    return $config;
  }

  public static function refreshToken() {
    $config = self::getConfig();
    $client = new Client(['base_uri' => 'https://launchpad.37signals.com/']);
    $options = [
      'form_params' => [
        "type" => 'refresh',
        'refresh_token' => $config['refresh_token'],
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
      ],
    ];
    try {
      $response = $client->post('/authorization/token', $options);
      $body = json_decode($response->getBody(), TRUE);
      \Drupal::state()->set('basecamp_api_access_token', $body['access_token']);
      \Drupal::logger('basecamp_api')->notice('Refreshed Basecamp API token.');
    }
    catch (RequestException $e) {
      if ($e->hasResponse()) {
        \Drupal::logger('basecamp_api')->error(Message::toString($e->getResponse()));
        return FALSE;
      }
    }
    return TRUE;
  }

}
