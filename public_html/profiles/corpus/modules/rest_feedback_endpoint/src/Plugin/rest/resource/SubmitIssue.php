<?php

namespace Drupal\rest_feedback_endpoint\Plugin\rest\resource;

use Drupal\basecamp_api\Basecamp;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to email a bug report.
 *
 * @RestResource(
 *   id = "rest_feedback_endpoint_submit_issue",
 *   label = @Translation("Submit an issue"),
 *   uri_paths = {
 *    "canonical" = "/submit-issue",
 *    "create" = "/submit-issue"
 *   }
 * )
 */
class SubmitIssue extends ResourceBase {

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
      $container->get('logger.factory')->get('rest_feedback_endpoint'),
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
    \Drupal::logger('rest_feedback_endpoint')->notice(serialize($data));
    $config = \Drupal::config('rest_feedback_endpoint.settings');
    if (!$config->get('on')) {
      return new ResourceResponse($response_status);
    }
    if (!empty($data['title']) && !empty($data['description'])) {
      $user = User::load($this->currentUser->id());
      $roles = $user->getRoles();
      $full_name = $user->get('field_full_name')->getString();
      $name = $full_name ?? $this->currentUser->getDisplayName();
      $reported_roles = array_diff($roles, ['authenticated', 'administrator']);

      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'rest_feedback_endpoint';
      $key = 'rest_feedback_endpoint';
      $to = $config->get('notification_email');
      $params['message'] = 'The user ' . $name . ' has reported an issue with the interface.' . PHP_EOL . PHP_EOL;
      $params['message'] .= 'SOURCE PAGE: ' . $data['url'] . PHP_EOL . PHP_EOL;
      $params['message'] .= 'DESCRIPTION: ' . Html::escape($data['description']) . PHP_EOL . PHP_EOL;
      $params['message'] .= 'USER ACCESS LEVEL: ' . implode(', ', $reported_roles) . PHP_EOL . PHP_EOL;
      $params['message'] .= 'TIMESTAMP: ' . date('F j, Y g:ia', time()) . PHP_EOL . PHP_EOL;
      $params['message'] .= 'PLATFORM/DEVICE: ' . $data['user_agent'] . PHP_EOL . PHP_EOL;
      if ($data['contact']) {
        $params['message'] .= 'CONTACT USER WITH UPDATES ABOUT THE ISSUE: yes (' . $this->currentUser->getEmail() . ')' . PHP_EOL . PHP_EOL;
      }
      else {
        $params['message'] .= 'CONTACT USER WITH UPDATES ABOUT THE ISSUE: no' . PHP_EOL . PHP_EOL;
      }
      $params['title'] = $config->get('subject_line_prefix') . Html::escape($data['title']);
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;
      $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

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
        $transaction = Basecamp::createTodo($project, $todolist, $data);
      }
    }
    if (!$transaction) {
      return new ModifiedResourceResponse(['Could not communicate with Basecamp'], 500);
    }
    return new ModifiedResourceResponse(['Sent'], 200);
  }

}
