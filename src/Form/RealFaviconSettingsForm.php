<?php

namespace Drupal\real_favicon\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\real_favicon\RealFaviconSync;

/**
 * Class RealFaviconSettingsForm.
 *
 * @package Drupal\real_favicon\Form
 *
 * @ingroup real_favicon
 */
class RealFaviconSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'real_favicon.settings',
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * Defines the settings form for Eventbrite Event entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $favicon_options = NULL, array $theme_options = NULL) {
    $config = $this->config('real_favicon.settings');
    $config_themes = $config->get('themes');

    $form['themes'] = array(
      '#type' => 'details',
      '#title' => $this->t('Theme Favicons'),
      '#description' => $this->t('A favicon can be set per theme.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    );

    foreach ($theme_options as $id => $name) {
      $form['themes'][$id] = array(
        '#type' => 'select',
        '#title' => $this->t('%name Favicon', ['%name' => $name]),
        '#options' => [0 => '- Use Drupal Default -'] + $favicon_options,
        '#default_value' => !empty($config_themes[$id]) && isset($favicon_options[$config_themes[$id]]) ? $config_themes[$id] : 0,
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('real_favicon.settings');
    parent::submitForm($form, $form_state);

    $config
      ->set('themes', array_filter($form_state->getValue('themes')))
      ->save();
  }

}
