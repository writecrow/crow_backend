<?php

namespace Drupal\crow_users\Plugin\rest\resource;

use Drupal\basecamp_api\Basecamp;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST endpoint to email an access level change.
 *
 * @RestResource(
 *   id = "crow_users_request_access_level_change",
 *   label = @Translation("Request access level change"),
 *   uri_paths = {
 *    "canonical" = "/user-change-request",
 *    "create" = "/user-change-request"
 *   }
 * )
 */
class ChangeAccessRequest extends ResourceBase {

  /**
   * A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('crow_users'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {
    // Permission to access this endpoint is determined via
    // Drupal permissioning, at /admin/people/permissions#module-rest.
    $response_status['status'] = FALSE;
    \Drupal::logger('access_level_change_request')->notice(serialize($data));
    $config = \Drupal::config('crow_users.settings');
    if (!$config->get('on')) {
      return new ResourceResponse($response_status);
    }
    if (!empty($data['role']) && !empty($data['description'])) {
      $user = User::load($this->currentUser->id());
      $roles = $user->getRoles();
      $full_name = $user->get('field_full_name')->getString();
      $name = $full_name ?? $this->currentUser->getDisplayName();
      $reported_roles = array_diff($roles, ['authenticated', 'administrator']);

      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'crow_users';
      $key = 'change_access_request';
      $to = Settings::get('corpus_users_bcc_email');
      $params['message'] = 'The user ' . $name . ' has requested an access level change.' . PHP_EOL . PHP_EOL;
      $params['message'] .= 'ROLE Requested: ' . $data['role'] . PHP_EOL . PHP_EOL;
      $params['message'] .= 'JUSTIFICATION: ' . Html::escape($data['description']) . PHP_EOL . PHP_EOL;
      $params['message'] .= 'CURRENT ROLES: ' . implode(', ', $reported_roles) . PHP_EOL . PHP_EOL;
      $params['message'] .= 'TIMESTAMP: ' . date('F j, Y g:ia', time()) . PHP_EOL . PHP_EOL;
      $params['title'] = 'Access level change request: ' . $name;
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;
      $response_status['status'] = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

      // @todo: add email for requestor.

      // Basecamp integration.
      $project = $config->get('basecamp_project');
      $todolist = $config->get('basecamp_list');
      $assignees = $config->get('basecamp_assignee_ids');
      if ($project && $todolist) {
        $data = [
          'content' => $params['title'],
          'description' => $params['message'],
          'due_on' => date('Y-m-d', strtotime('+7 days')),
          'notify' => TRUE,
        ];
        if (!empty($assignees)) {
          $data['assignee_ids'] = explode(',', $assignees);
        }
        \Drupal::logger('access_level_change_request')->notice('Sending todo for ' . $params['title']);
        Basecamp::createTodo($project, $todolist, $data);
      }
    }
    $response = new ResourceResponse($response_status);
    return $response;
  }

}
