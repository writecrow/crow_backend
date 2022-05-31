<?php

namespace Drupal\corpus_search\Plugin\rest\resource;


use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\corpus_search\Controller\CorpusSearch as Corpus;
use Drupal\corpus_search\Excerpt;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get corpus search results.
 *
 * This is modeled on https://www.drupal.org/project/drupal/issues/2884721.
 *
 * @RestResource(
 *   id = "offline_corpus_export",
 *   label = @Translation("Offline corpus export"),
 *   uri_paths = {
 *     "canonical" = "/corpus/full"
 *   }
 * )
 */
class OfflineCorpusExport extends ResourceBase {

  /**
   * A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
   * @param Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Request $current_request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
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
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest()
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
    $file = file_get_contents('IMG_202.zip');
    $response = new Response();
    $response->headers->set('Content-Type', 'application/zip');
    $response->headers->set('Content-Disposition', 'attachment; filename="crow_corpus.zip');
    $response->setContent($file);
    return $response;
  }

}
