<?php

/**
 * @file
 * Contains \Drupal\tmgmt\SourcePluginUiBase.
 */

namespace Drupal\tmgmt;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Default ui controller class for source plugin.
 *
 * @ingroup tmgmt_source
 */
class SourcePluginUiBase extends PluginBase implements SourcePluginUiInterface {

  /**
   * {@inheritdoc}
   */
  public function reviewForm(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function reviewDataItemElement(array $form, FormStateInterface $form_state, $data_item_key, $parent_key, array $data_item, JobItemInterface $item) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function reviewFormValidate(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    // Nothing to do here by default.
  }

  /**
   * {@inheritdoc}
   */
  public function reviewFormSubmit(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    // Nothing to do here by default.
  }

  /**
   * {@inheritdoc}
   */
  public function overviewForm(array $form, FormStateInterface $form_state, $type) {
    $form += $this->overviewSearchFormPart($form, $form_state, $type);

    $form['#attached']['library'][] = 'tmgmt/admin';

    $form['items'] = array(
      '#type' => 'tableselect',
      '#header' => $this->overviewFormHeader($type),
      '#empty' => $this->t('No source items matching given criteria have been found.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function overviewFormValidate(array $form, FormStateInterface $form_state, $type) {
    // Nothing to do here by default.
  }

  /**
   * {@inheritdoc}
   */
  public function overviewFormSubmit(array $form, FormStateInterface $form_state, $type) {
    // Nothing to do here by default.
  }

  /**
   * {@inheritdoc}
   */
  public function hook_views_default_views() {
    return array();
  }

  /**
   * Builds search form for entity sources overview.
   *
   * @param array $form
   *   Drupal form array.
   * @param FormStateInterface $form_state
   *   Drupal form_state array.
   * @param string $type
   *   Entity type.
   *
   * @return array
   *   Drupal form array.
   */
  public function overviewSearchFormPart(array $form, FormStateInterface $form_state, $type) {
    // Add entity type and plugin_id value into form array
    // so that it is available in the form alter hook.
    $form_state->set('entity_type', $type);
    $form_state->set('plugin_id', $this->pluginId);

    // Add search form specific styling.
    $form['#attached']['library'][] = 'tmgmt/source_search_form';

    $form['search_wrapper'] = array(
      '#prefix' => '<div class="tmgmt-sources-wrapper">',
      '#suffix' => '</div>',
      '#weight' => -15,
    );
    $form['search_wrapper']['search'] = array(
      '#tree' => TRUE,
    );
    $form['search_wrapper']['search_submit'] = array(
      '#type' => 'submit',
      '#value' => t('Search'),
      '#weight' => 90,
    );
    $form['search_wrapper']['search_cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#weight' => 100,
    );

    return $form;
  }

  /**
   * Gets languages form header.
   *
   * @return array
   *   Array with the languages for the header.
   */
  protected function getLanguageHeader() {
    $languages = array();
    foreach (\Drupal::languageManager()->getLanguages() as $langcode => $language) {
      $languages['langcode-' . $langcode] = array(
        'data' => $language->getName(),
      );
    }

    return $languages;
  }

  /**
   * Performs redirect with search params appended to the uri.
   *
   * In case of triggering element is edit-search-submit it redirects to
   * current location with added query string containing submitted search form
   * values.
   *
   * @param array $form
   *   Drupal form array.
   * @param FormStateInterface $form_state
   *   Drupal form_state array.
   * @param $type
   *   Entity type.
   *
   * @return bool
   *   Returns TRUE, if redirect has been set.
   */
  public function overviewSearchFormRedirect(array $form, FormStateInterface $form_state, $type) {
    if ($form_state->getTriggeringElement()['#id'] == 'edit-search-cancel') {
      $form_state->setRedirect('tmgmt.source_overview', array('plugin' => $this->pluginId, 'item_type' => $type));
      return TRUE;
    }
    elseif ($form_state->getTriggeringElement()['#id'] == 'edit-search-submit') {
      $query = array();

      foreach ($form_state->getValue('search') as $key => $value) {
        $query[$key] = $value;
      }
      $form_state->setRedirect('tmgmt.source_overview', array('plugin' => $this->pluginId, 'item_type' => $type), array('query' => $query));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the translation status render array with source and job item status.
   *
   * @param int $status
   *   The source status: original, missing, current or outofdate.
   * @param \Drupal\tmgmt\JobItemInterface|NULL $job_item
   *   The existing job item for the source.
   *
   * @return array
   *   The render array for displaying the status.
   */
  function buildTranslationStatus($status, JobItemInterface $job_item = NULL) {
    switch ($status) {
      case 'original':
        $label = t('Source language');
        $icon = 'core/misc/icons/bebebe/house.svg';
        break;

      case 'missing':
        $label = t('Not translated');
        $icon = 'core/misc/icons/bebebe/ex.svg';
        break;

      case 'outofdate':
        $label = t('Translation Outdated');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/outdated.svg';
        break;

      default:
        $label = t('Translation up to date');
        $icon = 'core/misc/icons/73b355/check.svg';
    }

    $build['source'] = [
      '#theme' => 'image',
      '#uri' => $icon,
      '#title' => $label,
      '#alt' => $label,
    ];

    // If we have an active job item, wrap it in a link.
    if ($job_item) {
      $states_labels = JobItem::getStates();
      $state_label = $states_labels[$job_item->getState()];
      $label = t('Active job item: @state', array('@state' => $state_label));
      $url = $job_item->toUrl();
      $job = $job_item->getJob();

      switch ($job_item->getState()) {
        case JobItem::STATE_ACTIVE:
          if ($job->isUnprocessed()) {
            $url = $job->toUrl();
            $label = t('Active job item: @state', array('@state' => $state_label));
          }
          $icon = drupal_get_path('module', 'tmgmt') . '/icons/hourglass.svg';
          break;

        case JobItem::STATE_REVIEW:
          $icon = drupal_get_path('module', 'tmgmt') . '/icons/ready.svg';
          break;
      }

      $url->setOption('query', \Drupal::destination()->getAsArray());
      $url->setOption('attributes', array('title' => $label));

      $item_icon = [
        '#theme' => 'image',
        '#uri' => $icon,
        '#title' => $label,
        '#alt' => $label,
      ];

      $build['job_item'] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $item_icon,
      ];
    }
    return $build;
  }

}
