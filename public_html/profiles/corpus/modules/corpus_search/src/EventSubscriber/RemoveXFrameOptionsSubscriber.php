<?php

namespace Drupal\corpus_search\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Executable\ExecutableManagerInterface;

/**
 * An event subscriber to remove the X-Frame-Options header.
 */
class RemoveXFrameOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Condition manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $conditionManager;

  /**
   * RemoveXFrameOptionsSubscriber constructor.
   *
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $condition_manager
   *   ConditionManager.
   */
  public function __construct(ExecutableManagerInterface $condition_manager) {
    $this->conditionManager = $condition_manager;
  }

  /**
   * Remove the X-Frame-Options header.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function RemoveXFrameOptions(ResponseEvent $event) {
    $xframe = FALSE;
    $pages = ['/corpus/search'];
    foreach ($pages as $page) {
      try {
        /* @var \Drupal\system\Plugin\Condition\RequestPath $condition */
        $condition = $this->conditionManager->createInstance('request_path');
        $condition->setConfiguration($page);
        if ($condition->evaluate()) {
          $xframe = TRUE;
        }
      } catch (PluginException $exception) {
        // Just ignore it, there's probably not much else to do.
      }
    }

    // If we got here we should be fine, but check it anyway.
    if ($xframe) {
      $response = $event->getResponse();
      $response->headers->remove('X-Frame-Options');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['RemoveXFrameOptions', -10];
    return $events;
  }

}
