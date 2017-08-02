<?php

namespace Drupal\real_favicon;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;

/**
 * Class RealFaviconManager.
 */
class RealFaviconManager implements RealFaviconManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * The real faicon configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The cache id.
   *
   * @var string
   */
  protected $cid = 'real_favicon.favicon';

  /**
   * The cache tags.
   *
   * @var array
   */
  protected $cacheTags = [
    'config:real_favicon.settings',
    'config:real_favicon_list',
  ];

  /**
   * Constructs a new RealFaviconManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('real_favicon.settings');
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags($theme_id) {
    $tags = NULL;
    $enabled = $this->config->get('themes');
    if (!empty($enabled[$theme_id])) {
      $cid = $this->cid . '.tags.' . $theme_id;
      if ($cache = $this->cache->get($cid)) {
        $tags = $cache->data;
      }
      else {
        if ($favicon = $this->loadFavicon($theme_id)) {
          $tags = $favicon->getValidTagsAsString();
        }
        $this->cache->set($cid, $tags, Cache::PERMANENT, $this->cacheTags);
      }
    }
    return $tags;
  }

  /**
   * Load a favicon.
   */
  public function loadFavicon($theme_id) {
    $favicon = NULL;
    $enabled = $this->config->get('themes');
    if (!empty($enabled[$theme_id])) {
      $favicon = $this->entityTypeManager->getStorage('real_favicon')->load($enabled[$theme_id]);
    }
    return $favicon;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->cacheTags;
  }

}
