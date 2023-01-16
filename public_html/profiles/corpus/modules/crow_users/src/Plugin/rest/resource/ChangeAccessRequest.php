<?php

namespace Drupal\crow_users\Plugin\rest\resource;

use Drupal\basecamp_api\Basecamp;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
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
      return new ModifiedResourceResponse(['Email delivery disabled'], 500);
    }
    if (empty($data['role']) && empty($data['description'])) {
      return new ModifiedResourceResponse(['Role or description not present in request'], 500);
    }
    $user = User::load($this->currentUser->id());
    $roles = $user->getRoles();
    $full_name = $user->get('field_full_name')->getString();
    $name = $full_name ?? $this->currentUser->getDisplayName();
    $current_roles = implode(', ', array_diff($roles, ['authenticated', 'administrator']));

    $mailManager = \Drupal::service('plugin.manager.mail');
    $send = TRUE;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $module = 'crow_users';

    // Email admins.
    $key = 'change_access_request';
    $to = Settings::get('corpus_users_bcc_email');
    $admin_email = $this->getAdminEmailText($name, $data['role'], $data['description'], $current_roles);
    $params['message'] = $admin_email;
    $params['title'] = 'Access level change request: ' . $name;
    $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    // Email requestor.
    $key = 'change_access_requestor';
    $params['message'] = $this->getRequestorEmailText($name, $data['role'], $user);
    $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    // Basecamp integration.
    $assignee_ids = $config->get('assignee_ids');
    if (!empty($assignee_ids)) {
      $data['assignee_ids'] = explode(',', $assignee_ids);
    }
    $project = $config->get('project');
    $todolist = $config->get('list');
    if ($project && $todolist) {
      $data = [
        'content' => $params['title'],
        'description' => $admin_email,
        'due_on' => date('Y-m-d', strtotime('+7 days')),
        'notify' => TRUE,
      ];
      $transaction = Basecamp::createTodo($project, $todolist, $data);
    }
    if (!$transaction) {
      return new ModifiedResourceResponse(['Could not communicate with Basecamp'], 500);
    }
    return new ModifiedResourceResponse(['Sent'], 200);
  }

  public function getAdminEmailText($name, $requested_role, $justification, $current_roles) {
    $body = [];
    $body[] = 'The user ' . $name . ' has requested an access level change.';
    $body[] = 'ROLE Requested: ' . $requested_role;
    $body[] = 'JUSTIFICATION: ' . Html::escape($justification);
    $body[] = 'CURRENT ROLE(S): ' . $current_roles;
    $body[] = 'REQUESTED ON: ' . date('F j, Y g:ia', time());
    return implode(PHP_EOL . PHP_EOL, $body);
  }

  public function getRequestorEmailText($name, $requested_role, $account) {
    $body = [];
    $body[] = $name . ',';
    $body[] = 'We have received your request for ' . $requested_role . 'access.';
    if ($requested_role === 'offline') {
      $survey = _get_crow_offline_survey($account);
      $body[] = 'Since you have requested offline access, you will now need to complete a training, at  ' . $survey . ' . Once that has been completed and reviewed, our team will continue evaluating your request.';
    }
    $body[] = 'Regards,';
    $body[] = 'Crow team';
    return implode(PHP_EOL . PHP_EOL, $body);
  }

}
