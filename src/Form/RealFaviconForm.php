<?php

namespace Drupal\real_favicon\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RealFaviconForm.
 *
 * @package Drupal\real_favicon\Form
 */
class RealFaviconForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $real_favicon = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $real_favicon->label(),
      '#description' => $this->t("Label for the Favicon."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $real_favicon->id(),
      '#machine_name' => [
        'exists' => '\Drupal\real_favicon\Entity\RealFavicon::load',
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#disabled' => !$real_favicon->isNew(),
    ];

    $form['tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tags'),
      '#default_value' => $real_favicon->getTagsAsString(),
      '#description' => t('Paste the code provided by <a href="@url" target="_blank">@url</a>. Make sure each link is on a separate line. It is fine to paste links with paths like "/apple-touch-icon-57x57.png" as these will be converted to the correct paths automatically.', ['@url' => 'http://realfavicongenerator.net/']),
      '#required' => TRUE,
    ];

    $validators = array(
      'file_validate_extensions' => array('zip'),
      'file_validate_size' => array(file_upload_max_size()),
    );
    $form['file'] = array(
      '#type' => 'file',
      '#title' => t('Upload a zip file from realfavicongenerator.net to install'),
      '#description' => array(
        '#theme' => 'file_upload_help',
        '#description' => t('For example: %filename from your local computer. This only needs to be done once.', array('%filename' => 'favicons.zip')),
        '#upload_validators' => $validators,
      ),
      '#size' => 50,
      '#upload_validators' => $validators,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    $this->file = file_save_upload('file', $form['file']['#upload_validators'], FALSE, 0);

    // Ensure we have the file uploaded.
    if (!$this->file && $this->entity->isNew()) {
      $form_state->setErrorByName('file', $this->t('File to import not found.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $real_favicon = $this->entity;

    if ($this->file) {
      try {
        $zip_path = $this->file->getFileUri();
        $real_favicon->setArchive($zip_path);
      }
      catch (Exception $e) {
        $form_state->setErrorByName('file', $e->getMessage());
        return;
      }
    }

    $status = $real_favicon->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Favicon.', [
          '%label' => $real_favicon->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Favicon.', [
          '%label' => $real_favicon->label(),
        ]));
    }
    $form_state->setRedirectUrl($real_favicon->urlInfo('collection'));
  }

}
