<?php

namespace Drupal\page_api;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Helper class.
 */
class RestHelper {

  /**
   * Validate content type string.
   *
   * @param string $contentType
   *   the content type.
   *
   * @return bool
   */
  public static function contentTypePermitted($contentType = NULL) {
    $allowedContentTypes = [
      'page',
    ];

    return in_array($contentType, $allowedContentTypes);
  }

  /**
   * Get CacheMetaData for content list or specific result.
   *
   * @param mixed $result
   *   processed content array.
   * @param string $entity_type
   *   (optional) defaults to node.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata cache metadata object
   */
  public function cacheMetaData($result, $entity_type = 'node') {
    $cacheMetaData = new CacheableMetadata();
    $cacheMetaData->setCacheContexts(['url']);

    if (empty($result) || !is_array($result)) {
      $result = [];
    }

    if ($entity_type === 'node') {
      return $this->cacheNodeMetaData($cacheMetaData, $result);
    }
  }

  /**
   * Get CacheMetaData for node list or specific result.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata cache metadata object
   */
  protected function cacheNodeMetaData(CacheableMetadata $cacheMetaData, $result = []) {
    if (!empty($result['nid'])) {
      $cacheMetaData->setCacheTags(['node:' . $result['nid']]);
      return $cacheMetaData;
    }

    $cacheMetaData->setCacheTags(['node_list']);

    return $cacheMetaData;
  }

}
