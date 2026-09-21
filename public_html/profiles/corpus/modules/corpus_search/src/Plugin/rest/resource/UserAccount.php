<?php

namespace Drupal\corpus_search\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Drupal\user\Entity\User;

/**
 * Provides a resource to return the account details of the current user.
 *
 * @RestResource(
 *   id = "corpus_search_user_account",
 *   label = @Translation("User account"),
 *   uri_paths = {
 *     "canonical" = "/user/account"
 *   }
 * )
 */
class UserAccount extends ResourceBase {

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
      $container->get('logger.factory')->get('example_rest'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($type = NULL) {
    /** @var  Drupal\user\Entity\User $user */
    $user = User::load($this->currentUser->id());
    $data['name'] = $user->getDisplayName();
    $data['roles'] = $user->getRoles();
    $data['email'] = $user->getEmail();
    $data['created'] = date('F j, Y', $user->getCreatedTime());
    $data['field_project_complete_date'] = $user->get('field_project_complete_date')->getString();
    $data['field_project_description'] = $user->get('field_project_description')->getString();
    $data['full_name'] = $user->get('field_full_name')->getString();
    $build = array(
      '#cache' => array(
      'max-age' => 0,
      ),
    );
    // Using ModifiedResourceResponse will enforce no caching in browser.
    $response = new ModifiedResourceResponse($data, 200);
    return $response;
  }

}
