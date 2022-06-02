<?php

namespace Drupal\crow_users\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;

/**
 * Defines FrontPageController class.
 */
class FrontPageController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
    $environment = Settings::get('crow_environment');
    switch ($environment) {
      case 'local':
        $url = 'https://localhost:4200';
        break;

      case 'development':
        $url = 'https://devcrow.corporaproject.org';
        break;

      case 'production':
        $url = 'https://crow.corporaproject.org';
        break;

      default:
        $url = 'https://crow.corporaproject.org';
        break;
    }
    return [
      '#type' => 'markup',
      '#markup' => $this->t('<h2>Proceed to the Corpus and Repository of Writing at <a href="@url">@url</a></h2>', ['@url' => $url]),
    ];
  }

}
