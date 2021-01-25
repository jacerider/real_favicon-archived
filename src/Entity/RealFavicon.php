<?php

namespace Drupal\real_favicon\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\File\FileSystemInterface;

/**
 * Defines the Favicon entity.
 *
 * @ConfigEntityType(
 *   id = "real_favicon",
 *   label = @Translation("Favicon"),
 *   handlers = {
 *     "list_builder" = "Drupal\real_favicon\RealFaviconListBuilder",
 *     "form" = {
 *       "add" = "Drupal\real_favicon\Form\RealFaviconForm",
 *       "edit" = "Drupal\real_favicon\Form\RealFaviconForm",
 *       "delete" = "Drupal\real_favicon\Form\RealFaviconDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\real_favicon\RealFaviconHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "real_favicon",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "tags",
 *     "archive",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/real-favicon/{real_favicon}",
 *     "add-form" = "/admin/structure/real-favicon/add",
 *     "edit-form" = "/admin/structure/real-favicon/{real_favicon}/edit",
 *     "delete-form" = "/admin/structure/real-favicon/{real_favicon}/delete",
 *     "collection" = "/admin/structure/real-favicon"
 *   }
 * )
 */
class RealFavicon extends ConfigEntityBase implements RealFaviconInterface {

  /**
   * The Favicon ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Favicon label.
   *
   * @var string
   */
  protected $label;

  /**
   * The manifest of this package.
   *
   * @var array
   */
  protected $manifest = [];

  /**
   * The folder where Real Favicons exist.
   *
   * @var string
   */
  protected $directory = 'public://favicon';

  /**
   * Set the tags from string.
   */
  public function setTagsAsString($string) {
    $tags = array_filter(explode(PHP_EOL, $string));
    foreach ($tags as $pos => $tag) {
      $tags[$pos] = trim($tag);
    }
    $this->set('tags', $tags);
  }

  /**
   * {@inheritDoc}
   */
  public function getTagsAsString() {
    $tags = $this->get('tags');
    return $tags ? implode(PHP_EOL, $tags) : '';
  }

  /**
   * Get the tags.
   */
  public function getTags() {
    return $this->get('tags');
  }

  /**
   * Get the manifest.
   */
  public function getManifest() {
    if (empty($this->manifest)) {
      $this->manifest = [];
      $path = $this->getDirectory() . '/manifest.json';
      if (file_exists($path)) {
        $data = file_get_contents($path);
        $this->manifest = Json::decode($data);
      }
    }
    return $this->manifest;
  }

  /**
   * Get the largest manifest image.
   */
  public function getManifestLargeImage() {
    $image = '';
    if ($manifest = $this->getManifest()) {
      $size = 0;
      foreach ($manifest['icons'] as $icon) {
        $icon_size = explode('x', $icon['sizes']);
        if ($icon_size[0] > $size) {
          $image = $this->getDirectory() . $icon['src'];
        }
      }
    }
    else {
      // New version of real favicon do not generate a manifest.
      return $this->getDirectory() . '/apple-touch-icon.png';
    }
    return $image;
  }

  /**
   * {@inheritDoc}
   */
  public function setArchive($zip_path) {
    $data = strtr(base64_encode(addslashes(gzcompress(serialize(file_get_contents($zip_path)), 9))), '+/=', '-_,');
    $parts = str_split($data, 200000);
    $this->set('archive', $parts);
  }

  /**
   * Get the archive from base64 encoded string.
   */
  public function getArchive() {
    $data = implode('', $this->get('archive'));
    return unserialize(gzuncompress(stripslashes(base64_decode(strtr($data, '-_,', '+/=')))));
  }

  /**
   * Get a favicon image.
   */
  public function getThumbnail($image_name = 'favicon-16x16.png') {
    return $this->getDirectory() . '/' . $image_name;
  }

  /**
   * Return the location where Iconifys exist.
   *
   * @return string
   *   The directory path.
   */
  public function getDirectory() {
    return $this->directory . '/' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!$this->isNew()) {
      $original = $storage->loadUnchanged($this->getOriginalId());
    }
    /** @var \Drupal\real_favicon\Entity\RealFaviconInterface $original */

    if (is_string($this->get('tags'))) {
      $this->setTagsAsString($this->get('tags'));
    }

    if (!$this->get('archive')) {
      throw new EntityMalformedException('Real favicon package is required.');
    }
    if ($this->isNew() || $original->get('archive') !== $this->get('archive')) {
      $this->archiveDecode();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    foreach ($entities as $entity) {
      /** @var \Drupal\real_favicon\Entity\RealFaviconInterface $entity */
      $file_system->deleteRecursive($entity->getDirectory());
      // Clean up empty directory. Will fail silently if it is not empty.
      @rmdir($entity->directory);
    }
  }

  /**
   * Take base64 encoded archive and save it to a temporary file for extraction.
   */
  protected function archiveDecode() {
    $data = $this->getArchive();
    $zip_path = 'temporary://' . $this->id() . '.zip';
    file_put_contents($zip_path, $data);
    $this->archiveExtract($zip_path);
  }

  /**
   * Properly extract and store an IcoMoon zip file.
   *
   * @param string $zip_path
   *   The absolute path to the zip file.
   */
  public function archiveExtract($zip_path) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    /** @var \Drupal\Core\Archiver\ArchiverManager $archiver_manager */
    $archiver_manager = \Drupal::service('plugin.manager.archiver');
    $archiver = $archiver_manager->getInstance(['filepath' => $zip_path]);
    if (!$archiver) {
      throw new \Exception(t('Cannot extract %file, not a valid archive.', ['%file' => $zip_path]));
    }

    $directory = $this->getDirectory();
    $file_system->deleteRecursive($directory);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $archiver->extract($directory);

    \Drupal::messenger()->addMessage(t('Real Favicon package has been successfully %op.', ['%op' => ($this->isNew() ? t('updated') : t('added'))]));
  }

  /**
   * Get valid tags as strings.
   */
  public function getValidTagsAsString() {
    return implode(PHP_EOL, $this->getValidTags()) . PHP_EOL;
  }

  /**
   * Get valid tags.
   */
  public function getValidTags() {
    $base_path = base_path();
    $html = $this->getTagsAsString();
    $found = [];
    $missing = [];

    $dom = new \DOMDocument();
    $dom->loadHTML($html);

    // DRUPAL_ROOT contains the sub directory of the Drupal install (if present),
    // in our case we do not want this as $file_path already contains this.
    $docroot = preg_replace('/' . preg_quote($base_path, '/') . '$/', '/', DRUPAL_ROOT);

    // Find all the apple touch icons.
    $tags = $dom->getElementsByTagName('link');
    foreach ($tags as $tag) {
      $file_path = $this->normalizePath($tag->getAttribute('href'));
      $tag->setAttribute('href', $file_path);

      if (file_exists($docroot . $file_path) && is_readable($docroot . $file_path)) {
        $found[] = $dom->saveXML($tag);
      }
      else {
        $missing[] = $dom->saveXML($tag);
      }
    }

    // Find any Windows 8 meta tags.
    $tags = $dom->getElementsByTagName('meta');
    foreach ($tags as $tag) {
      $name = $tag->getAttribute('name');

      // We only validate the image file.
      if ($name === 'msapplication-TileImage') {
        $file_path = $this->normalizePath($tag->getAttribute('content'));
        $tag->setAttribute('content', $file_path);

        if (file_exists($docroot . $file_path) && is_readable($docroot . $file_path)) {
          $found[] = $dom->saveXML($tag);
        }
        else {
          $missing[] = $dom->saveXML($tag);
        }
      }
      // Just add any other meta tags and assume they contain no images.
      else {
        $found[] = $dom->saveXML($tag);
      }
    }
    return $found;
  }

  /**
   * Normalize path.
   *
   * @return string
   *   The normalized path.
   */
  protected function normalizePath($file_path) {
    return file_url_transform_relative(file_create_url($this->getDirectory() . $file_path));
  }

}
