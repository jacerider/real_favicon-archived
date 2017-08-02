<?php

namespace Drupal\real_favicon;

/**
 * Interface RealFaviconManagerInterface.
 */
interface RealFaviconManagerInterface {

  /**
   * Get cache tags for a theme as a string.
   *
   * @param string $theme_id
   *   The theme id.
   *
   * @return string|null
   *   The tags as HTML ready for output.
   */
  public function getTags($theme_id);

  /**
   * Get the real favicon entity assiciated with a theme.
   *
   * @param string $theme_id
   *   The theme id.
   *
   * @return \Drupal\real_favicon\Entity\RealFavicon|null
   *   The real favicon entity.
   */
  public function loadFavicon($theme_id);

  /**
   * Get the cache tags for a theme favicon.
   */
  public function getCacheTags();

}
