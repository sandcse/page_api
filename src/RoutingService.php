<?php

namespace Drupal\page_api;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RoutingService.
 *
 * @package Drupal\page_api
 */
class RoutingService {

  /**
   * Route collection.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   */
  public function routes() {
    $routeCollection = new RouteCollection();

    $routeCollection->add('page_api.lookup', new Route(
      // uuid/path lookup route.
      '/page_json/{key}/{id}',

      // Route configuration parameters.
      [
        '_controller' => '\Drupal\page_api\ApiController::lookup',
      ],

      // Route permission reqs.
      [
        '_permission'  => 'access content',
      ]
    ));

    return $routeCollection;
  }

}
