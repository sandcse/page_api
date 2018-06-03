<?php

namespace Drupal\page_api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\Entity\Node;
use Exception;

/**
 * Provides JSON of node.
 */
class ApiController extends ControllerBase {

  /**
   * @var Drupal\page_api\RestHelper
   */
  protected $restHelper;

  /**
   * Constructor.
   */
  public function __construct(RestHelper $restHelper) {
    $this->restHelper = $restHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('page_api.rest_helper')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function lookup($key = '', $id = '') {

    $siteapikey = \Drupal::config('system.site')->get('siteapikey');

    if ($siteapikey !== $key) {
      // Return 403 Access Denied page.
      return new CacheableJsonResponse(['error' => 'Access denied.'], 403);
    }

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);

    try {

      if (!empty($node) && $this->restHelper->contentTypePermitted($node->getType())) {

        $result = \Drupal::entityManager()->getStorage('node')->load($id);

        $json_response = $this->processNode($node, $options = []);

        // Respond with the json representation of the node.
        $response = new CacheableJsonResponse($json_response);
        $response->addCacheableDependency($this->restHelper->cacheMetaData($json_response));
        // Return new JsonResponse($json_response);
        return $response;
      }
      else {
        // When other content types.
        return new CacheableJsonResponse(['error' => 'Access denied.'], 403);
      }
    }
    catch (Exception $e) {
      return $this->handleException($e);
    }
  }

  /**
   * Process all fields in a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   the node object.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return array node information in clean format for REST
   */
  protected function processNode(Node $node, $options = []) {
    $view = [];
    $fieldDefinitions = \Drupal::service('entity.manager')->getFieldDefinitions('node', $node->getType());
    $storageDefinitions = \Drupal::service('entity.manager')->getFieldStorageDefinitions('node');

    foreach ($fieldDefinitions as $name => $fieldDefinition) {

      $options['fieldDefinition'] = $fieldDefinition;
      $options['storageDefinition'] = $storageDefinitions[$name];

      $supported = in_array($fieldDefinition->getType(), array_keys(self::supportedFieldTypes()));
      $ignored = in_array($name, self::ignoredFieldNames());

      if ($supported && !$ignored) {

        $view[$name] = $this->processField($node->{$name}, $options);
      }
    }

    return $view;
  }

  /**
   * General case: process a field value. Will automatically choose correct
   *  "formatter" method.
   *
   * @see self::supportedFieldTypes()
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   the field item list.
   * @param array options
   *   - FieldDefinitionInterface $fieldDefinition field instance info
   *     used to get field instance information.
   *
   * @return mixed "formatted" value of the field
   */
  protected function processField(FieldItemListInterface $field, $options = []) {
    $method = self::supportedFieldTypes()[$options['fieldDefinition']->getType()];
    return $this->{$method}($field, $options);
  }

  /**
   * Get simple value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return string simple string value
   */
  protected function getFieldValue(FieldItemListInterface $field, $options = []) {
    return $field->value;
  }

  /**
   * Get simple integer value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return int
   */
  protected function getIntFieldValue(FieldItemListInterface $field, $options = []) {
    return intval($field->value);
  }

  /**
   * Get simple date value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   *
   * @return string simple string value
   */
  protected function getDateFieldValue(FieldItemListInterface $field, $options = []) {
    return \Drupal::service('date.formatter')->format($field->value, 'html_datetime');
  }

  /**
   * Methods for processing different field types.
   *
   * @return array methods for handling differnent field types.
   */
  protected static function supportedFieldTypes() {
    return [
      'string' => 'getFieldValue',
      'string_long' => 'getFieldValue',
      'text' => 'getFieldValue',
      'text_with_summary' => 'getFieldValue',
      'text_long' => 'getFieldValue',
      'created' => 'getDateFieldValue',
      'changed' => 'getDateFieldValue',
      'path' => 'getPathFieldValue',
      'float' => 'getFloatFieldValue',
      'uuid' => 'getFieldValue',
      'integer' => 'getIntFieldValue',
    ];
  }

  /**
   * Ignored fields used processing nodes.
   *
   * @return array list of ignored field names.
   */
  protected static function ignoredFieldNames() {
    return [
      'parent',
      'tid',
      'vid',
      'langcode',
      'uid',
      'promote',
      'sticky',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'default_langcode',
      'publish_on',
      'unpublish_on',
    ];
  }

  /**
   * Get path alias field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   field item list.
   * @param array $options
   *   - boolean $recurse references are recursively dereferenced
   *   - integer $maxDepth levels of recursion.
   *
   * @return string path alias
   */
  protected function getPathFieldValue(FieldItemListInterface $field, $options = []) {
    $entity = $field->getEntity();
    $source = $entity->toUrl()->getInternalPath();
    $lang = $entity->language()->getId();
    $path = \Drupal::service('path.alias_storage')->lookupPathAlias('/' . $source, $lang);
    return preg_replace('/^\//', '', $path);
  }

  /**
   * Handle Exceptions.
   *
   * @param \Exception $e
   *   the exception.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  protected function handleException(Exception $e) {
    if ($e instanceof Rest404Exception) {
      return new CacheableJsonResponse(['error' => 'Error 404 not found'], 404);
    }
    elseif ($e instanceof Rest403Exception) {
      return new CacheableJsonResponse(['error' => 'Access denied.'], 403);
    }
    return new CacheableJsonResponse(['error' => 'Internal server error.'], 500);
  }

}
