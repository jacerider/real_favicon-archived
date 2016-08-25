<?php

namespace Drupal\real_favicon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;

/**
 * Provides a listing of Favicon entities.
 */
class RealFaviconListBuilder extends ConfigEntityListBuilder {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('theme_handler')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, ThemeHandlerInterface $theme_handler) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['image'] = '';
    $header['label'] = $this->t('Name');
    $header['id'] = $this->t('ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['image'] = [
      'data' => [
        '#theme' => 'image',
        '#uri' => $entity->getThumbnail(),
      ]
    ];
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $render = parent::render();

    $favicon_options = [];
    foreach ($this->load() as $real_favicon) {
      $favicon_options[$real_favicon->id()] = $real_favicon->label();
    }

    if (!empty($favicon_options)) {
      $themes = $themes = $this->themeHandler->listInfo();
      uasort($themes, 'system_sort_modules_by_info_name');

      $theme_options = [];
      foreach ($themes as &$theme) {
        if (!empty($theme->info['hidden'])) {
          continue;
        }
        if (!empty($theme->status)) {
          $theme_options[$theme->getName()] = $theme->info['name'];
        }
      }
      $render['form'] = \Drupal::formBuilder()->getForm('Drupal\real_favicon\Form\RealFaviconSettingsForm', $favicon_options, $theme_options);
    }

    return $render;
  }

}
