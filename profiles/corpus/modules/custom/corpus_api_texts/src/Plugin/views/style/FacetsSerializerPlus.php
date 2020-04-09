<?php

namespace Drupal\corpus_api_texts\Plugin\views\style;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\facets_rest\Plugin\views\style\FacetsSerializer;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "facets_serializer_plus",
 *   title = @Translation("Facets serializer Plus"),
 *   help = @Translation("Adds Facets results and Pager/Results information to output"),
 *   display_types = {"data"}
 * )
 */
class FacetsSerializerPlus extends FacetsSerializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      // Keep track of the current rendered row, like every style plugin has to
      // do.
      // @see \Drupal\views\Plugin\views\style\StylePluginBase::renderFields
      $this->view->row_index = $row_index;
      $rows['search_results'][] = $this->view->rowPlugin->render($row);
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    // Processing facets.
    $facetsource_id = "search_api:views_rest__{$this->view->id()}__{$this->view->getDisplay()->display['id']}";
    $facets = $this->facetsManager->getFacetsByFacetSourceId($facetsource_id);
    $this->facetsManager->updateResults($facetsource_id);

    $processed_facets = [];
    foreach ($facets as $facet) {
      $processed_facets[] = $this->facetsManager->build($facet);
    }

    $rows['facets'] = array_values($processed_facets);

    $pager = $this->view->pager;
    $class = get_class($pager);
    $current_page = $pager->getCurrentPage();
    $items_per_page = $pager->getItemsPerPage();
    $total_items = $pager->getTotalItems();
    $total_pages = 0;
    if(!in_array($class, ['Drupal\views\Plugin\views\pager\None', 'Drupal\views\Plugin\views\pager\Some'])){
      $total_pages = $pager->getPagerTotal();
    }

    $rows['pager'] = [
      'current_page' => $current_page,
      'total_items' => $total_items,
      'total_pages' => $total_pages,
      'items_per_page' => $items_per_page,
    ];

    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}
