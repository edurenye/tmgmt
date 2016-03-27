<?php

/**
 * @file
 * Contains \Drupal\tmgmt\Form\JobItemForm.
 */

namespace Drupal\tmgmt\Form;

use Drupal\Component\Diff\Diff;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\filter\Entity\FilterFormat;
use \Drupal\Core\Diff\DiffFormatter;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\TranslatorRejectDataInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Form controller for the job item edit forms.
 *
 * @ingroup tmgmt_job
 */
class JobItemForm extends TmgmtFormBase {

  /**
   * @var \Drupal\tmgmt\JobItemInterface
   */
  protected $entity;

  /**
   * Overrides Drupal\Core\Entity\EntityForm::form().
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $item = $this->entity;

    $form['#title'] = $this->t('Job item @source_label', array('@source_label' => $item->getSourceLabel()));

    $form['info'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('tmgmt-ui-job-info', 'clearfix')),
      '#weight' => 0,
    );

    $url = $item->getSourceUrl();
    $form['info']['source'] = array(
      '#type' => 'item',
      '#title' => t('Source'),
      '#markup' => $url ? \Drupal::l($item->getSourceLabel(),$url) : $item->getSourceLabel(),
      '#prefix' => '<div class="tmgmt-ui-source tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    $form['info']['sourcetype'] = array(
      '#type' => 'item',
      '#title' => t('Source type'),
      '#markup' => $item->getSourceType(),
      '#prefix' => '<div class="tmgmt-ui-source-type tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    $form['info']['source_language'] = array(
      '#type' => 'item',
      '#title' => t('Source language'),
      '#markup' => $item->getJob()->getSourceLanguage()->getName(),
      '#prefix' => '<div class="tmgmt-ui-source-language tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    $form['info']['target_language'] = array(
      '#type' => 'item',
      '#title' => t('Target language'),
      '#markup' => $item->getJob()->getTargetLanguage()->getName(),
      '#prefix' => '<div class="tmgmt-ui-target-language tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    $form['info']['changed'] = array(
      '#type' => 'item',
      '#title' => t('Last change'),
      '#value' => $item->getChangedTime(),
      '#markup' => format_date($item->getChangedTime()),
      '#prefix' => '<div class="tmgmt-ui-changed tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );
    $states = JobItem::getStates();
    $form['info']['state'] = array(
      '#type' => 'item',
      '#title' => t('State'),
      '#markup' => $states[$item->getState()],
      '#prefix' => '<div class="tmgmt-ui-item-state tmgmt-ui-info-item">',
      '#suffix' => '</div>',
      '#value' => $item->getState(),
    );
    $job = $item->getJob();
    $url = $job->urlInfo();
    $form['info']['job'] = array(
      '#type' => 'item',
      '#title' => t('Job'),
      '#markup' => \Drupal::l($job->label(), $url),
      '#prefix' => '<div class="tmgmt-ui-job tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    // Display selected translator for already submitted jobs.
    if (!$item->getJob()->isSubmittable()) {
      $form['info']['translator'] = array(
        '#type' => 'item',
        '#title' => t('Provider'),
        '#markup' => $job->getTranslatorLabel(),
        '#prefix' => '<div class="tmgmt-ui-translator tmgmt-ui-info-item">',
        '#suffix' => '</div>',
      );
    }

    // Actually build the review form elements...
    $form['review'] = array(
      '#type' => 'container',
    );
    // Build the review form.
    $data = $item->getData();
    $this->trackChangedSource(\Drupal::service('tmgmt.data')->flatten($data), $form_state);
    // Need to keep the first hierarchy. So flatten must take place inside
    // of the foreach loop.
    $zebra = 'even';
    foreach (Element::children($data) as $key) {
      $review_element = $this->reviewFormElement($form_state, \Drupal::service('tmgmt.data')->flatten($data[$key], $key), $item, $zebra, $key);
      if ($review_element) {
        $form['review'][$key] = $review_element;
      }
    }

    if ($view =  entity_load('view', 'tmgmt_job_item_messages')) {
      $form['messages'] = array(
        '#type' => 'details',
        '#title' => $view->label(),
        '#open' => FALSE,
        '#weight' => 50,
      );
      $form['messages']['view'] = $view->getExecutable()->preview('block', array($item->id()));
    }

    $form['#attached']['library'][] = 'tmgmt/admin';
    // The reject functionality has to be implement by the translator plugin as
    // that process is completely unique and custom for each translation service.

    // Give the source ui controller a chance to affect the review form.
    $source = $this->sourceManager->createUIInstance($item->getPlugin());
    $form = $source->reviewForm($form, $form_state, $item);
    // Give the translator ui controller a chance to affect the review form.
    if ($item->getTranslator()) {
      $plugin_ui = $this->translatorManager->createUIInstance($item->getTranslator()->getPluginId());
      $form = $plugin_ui->reviewForm($form, $form_state, $item);
    }
    $form['footer'] = tmgmt_color_review_legend();
    return $form;
  }

  protected function actions(array $form, FormStateInterface $form_state) {
    $item = $this->entity;

    // Add the form actions as well.
    $actions['accept'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => t('Save as completed'),
      '#access' => $item->isNeedsReview() && !$form_state->has('accept_item'),
      '#validate' => array('::validateForm', '::validateJobItem'),
      '#submit' => array('::submitForm', '::save'),
    );
    $actions['save'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#access' => !$item->isAccepted(),
      '#submit' => array('::submitForm', '::save'),
    );
    if ($item->isActive()) {
      $actions['save']['#button_type'] = 'primary';
    }
    $actions['validate'] = array(
      '#type' => 'submit',
      '#value' => t('Validate'),
      '#access' => !$item->isAccepted(),
      '#validate' => array('::validateForm', '::validateJobItem'),
      '#submit' => array('::submitForm', '::submitRebuild'),
    );
    $actions['validate_html'] = array(
      '#type' => 'submit',
      '#value' => t('Validate HTML tags'),
      '#access' => !$item->isAccepted(),
      '#validate' => ['::validateTags'],
      '#submit' => ['::submitForm'],
    );
    if ($item->getSourcePlugin() instanceof SourcePreviewInterface && $item->getSourcePlugin()->getPreviewUrl($item)) {
      $actions['preview'] = [
        '#type' => 'link',
        '#title' => t('Preview'),
        '#url' => $item->getSourcePlugin()->getPreviewUrl($item),
        '#attributes' => [
          'target' => '_blank',
        ],
      ];
    }
    return $actions;
  }

  /**
   * Gets the translatable fields of a given job item.
   *
   * @param array $form
   *   The form array.
   *
   * @return array $fields
   *   Returns the translatable fields of the job item.
   */
  private function getTranslatableFields(array $form) {
    $fields = [];
    foreach ($form['review'] as $parent_key => $value) {
      if (is_array($value)) {
        foreach ($value as $key => $data) {
          if (isset($data['translation'])) {
            $fields[$key] = ['parent_key' => $parent_key, 'data' => $data];
          }
        }
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    /** @var JobItem $item */
    $item = $this->buildEntity($form, $form_state);
    // First invoke the validation method on the source controller.
    $source_ui = $this->sourceManager->createUIInstance($item->getPlugin());
    $source_ui->reviewFormValidate($form, $form_state, $item);
    // Invoke the validation method on the translator controller (if available).
    if ($item->hasTranslator()) {
      $translator_ui = $this->translatorManager->createUIInstance($item->getTranslator()->getPluginId());
      $translator_ui->reviewFormValidate($form, $form_state, $item);
    }
  }

  /**
   * Form validate callback to validate the job item.
   */
  public function validateJobItem(array &$form, FormStateInterface $form_state) {
    foreach ($this->getTranslatableFields($form) as $key => $value) {
      $parent_key = $value['parent_key'];
      // If has HTML tags will be an array.
      if (isset($value['data']['translation']['value'])) {
        $label = $value['data']['translation']['value']['#value'];
      }
      else {
        $label = $value['data']['translation']['#value'];
      }

      // Validate that is not empty.
      if (empty($label)) {
        $form_state->setError($form['review'][$parent_key][$key]['translation'], $this->t('The field is empty.'));
        continue;
      }
      else {
        drupal_set_message(t('Validation completed successfully.'));
      }
    }
  }

  /**
   * Validate that the element is not longer than the max length.
   *
   * @param array $element
   *   The input element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateMaxLength(array $element, FormStateInterface &$form_state) {
    if (isset($element['#max_length'])
      && ($element['#max_length'] < strlen($element['#value']))) {
      $form_state->setError($element,
        $this->t('The field has @size characters while the limit is @limit.', [
          '@size' => strlen($element['#value']),
          '@limit' => $element['#max_length'],
        ])
      );
    }
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function save(array $form, FormStateInterface $form_state) {
    $item = $this->entity;
    // First invoke the submit method on the source controller.
    $source_ui = $this->sourceManager->createUIInstance($item->getPlugin());
    $source_ui->reviewFormSubmit($form, $form_state, $item);
    // Invoke the submit method on the translator controller (if available).
    if ($item->getTranslator()){
      $translator_ui = $this->translatorManager->createUIInstance($item->getTranslator()->getPluginId());
      $translator_ui->reviewFormSubmit($form, $form_state, $item);
    }
    // Write changes back to item.
    foreach ($form_state->getValues() as $key => $value) {
      if (is_array($value) && isset($value['translation'])) {
        // Update the translation, this will only update the translation in case
        // it has changed. We have two different cases, the first is for nested
        // texts.
        if (is_array($value['translation'])) {
          $data = array(
            '#text' => $value['translation']['value'],
            '#origin' => 'local',
          );
        }
        else {
          $data = array(
            '#text' => $value['translation'],
            '#origin' => 'local',
          );
        }
        if ($data['#text'] == '' && $item->isActive() && $form_state->getTriggeringElement()['#value'] != '✓') {
          $data = NULL;
          continue;
        }

        $data_service = \Drupal::service('tmgmt.data');
        $current_data_status = $item->getData($data_service->ensureArrayKey($key))['#status'];

        $item->addTranslatedData($data, $key, $current_data_status);
      }
    }
    // Check if the user clicked on 'Accept', 'Submit' or 'Reject'.
    if (!empty($form['actions']['accept']) && $form_state->getTriggeringElement()['#value'] == $form['actions']['accept']['#value']) {
      $item->acceptTranslation();
      // Print all messages that have been saved while accepting the reviewed
      // translation.
      foreach ($item->getMessagesSince() as $message) {
        // Ignore debug messages.
        if ($message->getType() == 'debug') {
          continue;
        }
        if ($text = $message->getMessage()) {
          drupal_set_message(SafeMarkup::format($text, []), $message->getType());
        }
      }
    }
    if ($form_state->getTriggeringElement()['#value'] == $form['actions']['save']['#value'] && isset($data)) {
      if ($item->getSourceUrl()) {
        $message = t('The translation for <a href=:job>@job_title</a> has been saved successfully.', [
          ':job' => $item->getSourceUrl()->toString(),
          '@job_title' => $item->label(),
        ]);
      }
      else {
        $message = t('The translation has been saved successfully.');
      }
      drupal_set_message($message);
    }
    $item->save();
    $item->getJob()->isContinuous() ? $form_state->setRedirect('entity.tmgmt_job_item.canonical', ['tmgmt_job_item' => $item->id()]) : $form_state->setRedirectUrl($item->getJob()->urlInfo());
  }

  /**
   * Build form elements for the review form using flattened data items.
   *
   * @todo Mention in the api documentation that the char '|' is not allowed in
   * field names.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $data
   *   Flattened array of translation data items.
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item related to this data.
   * @param string $zebra
   *   String containing either odd or even. This is used to style the each
   *   translation item with alternating colors.
   * @param string $parent_key
   *   The key for $data.
   *
   * @return array|NULL
   *   Render array with the form element, or NULL if the text is not set.
   */
  function reviewFormElement(FormStateInterface $form_state, $data, JobItemInterface $job_item, &$zebra, $parent_key) {
    $flip = array(
      'even' => 'odd',
      'odd' => 'even',
    );
    $form = NULL;

    foreach (Element::children($data) as $key) {
      // The char sequence '][' confuses the form API so we need to replace it.
      $target_key = str_replace('][', '|', $key);
      if (isset($data[$key]['#text']) && \Drupal::service('tmgmt.data')->filterData($data[$key])) {
        $form['#theme'] = 'tmgmt_translator_review_form';
        $form['#ajaxid'] = tmgmt_review_form_element_ajaxid($parent_key);
        $zebra = $flip[$zebra];
        $form[$target_key] = array(
          '#tree' => TRUE,
          '#theme' => 'tmgmt_translator_review_form_element',
          '#parent_label' => $data[$key]['#parent_label'],
          '#zebra' => $zebra,
        );
        $form[$target_key]['status'] = $this->buildStatusRenderArray($job_item->isAccepted() ? TMGMT_DATA_ITEM_STATE_ACCEPTED : $data[$key]['#status']);
        $form[$target_key]['actions'] = array(
          '#type' => 'container',
        );

        // Check if the field has a text format attached.
        if (!empty($data[$key]['#format'])) {
          $format_id = $data[$key]['#format'];
          /** @var \Drupal\filter\Entity\FilterFormat $format */
          $format = FilterFormat::load($format_id);

          if (!$format || !$format->access('use')) {
            $form[$target_key]['actions']['#access'] = FALSE;
            $form_state->set('accept_item', FALSE);
          }
        }
        if (!$job_item->isAccepted()) {
          if ($data[$key]['#status'] != TMGMT_DATA_ITEM_STATE_REVIEWED) {
            $form[$target_key]['actions']['reviewed'] = array(
              '#type' => 'submit',
              // Unicode character &#x2713 CHECK MARK
              '#value' => '✓',
              '#attributes' => array('title' => t('Reviewed')),
              '#name' => 'reviewed-' . $target_key,
              '#submit' => [
                '::save',
                'tmgmt_translation_review_form_update_state',
              ],
              '#limit_validation_errors' => array(array($form['#ajaxid']), array($target_key)),
              '#ajax' => array(
                'callback' => array($this, 'ajaxReviewForm'),
                'wrapper' => $form['#ajaxid'],
              ),
            );
          }
          else {
            $form[$target_key]['actions']['unreviewed'] = array(
              '#type' => 'submit',
              // Unicode character &#x2713 CHECK MARK
              '#value' => '✓',
              '#attributes' => array('title' => t('Not reviewed'), 'class' => array('unreviewed')),
              '#name' => 'unreviewed-' . $target_key,
              '#submit' => [
                '::save',
                'tmgmt_translation_review_form_update_state',
              ],
              '#limit_validation_errors' => array(array($form['#ajaxid']), array($target_key)),
              '#ajax' => array(
                'callback' => array($this, 'ajaxReviewForm'),
                'wrapper' => $form['#ajaxid'],
              ),
            );
          }
          if ($job_item->hasTranslator() && $job_item->getTranslatorPlugin() instanceof TranslatorRejectDataInterface && $data[$key]['#status'] != TMGMT_DATA_ITEM_STATE_PENDING) {
            $form[$target_key]['actions']['reject'] = array(
              '#type' => 'submit',
              // Unicode character &#x2717 BALLOT X
              '#value' => '✗',
              '#attributes' => array('title' => t('Reject')),
              '#name' => 'reject-' . $target_key,
              '#submit' => [
                '::save',
                'tmgmt_translation_review_form_update_state',
              ],
            );
          }

          if (!empty($data[$key]['#translation']['#text_revisions'])) {
            $form[$target_key]['actions']['revert'] = array(
              '#type' => 'submit',
              // Unicode character U+21B6 ANTICLOCKWISE TOP SEMICIRCLE ARROW
              '#value' => '↶',
              '#attributes' => array('title' => t('Revert to previous revision'), 'class' => array('reset-above')),
              '#name' => 'revert-' . $target_key,
              '#data_item_key' => $key,
              '#submit' => array('tmgmt_translation_review_form_revert'),
              '#ajax' => array(
                'callback' => array($this, 'ajaxReviewForm'),
                'wrapper' => $form['#ajaxid'],
              ),
            );
            $form[$target_key]['actions']['reviewed']['#attributes'] = array('class' => array('reviewed-below'));
          }
        }
        if (!empty($data[$key]['#translation']['#text_revisions'])) {
          $revisions = array();

          foreach ($data[$key]['#translation']['#text_revisions'] as $revision) {
            $revisions[] = t('Origin: %origin, Created: %created<br />%text', array(
              '%origin' => $revision['#origin'],
              '%created' => format_date($revision['#timestamp']),
              '%text' => Xss::filter($revision['#text']),
            ));
          }
          $form[$target_key]['below']['revisions_wrapper'] = array(
            '#type' => 'details',
            '#title' => t('Translation revisions'),
            '#open' => TRUE,
          );
          $form[$target_key]['below']['revisions_wrapper']['revisions'] = array(
            '#theme' => 'item_list',
            '#items' => $revisions,
          );
        }

        // Manage the height of the texteareas, depending on the lenght of the
        // description. The minimum number of rows is 3 and the maximum is 15.
        $rows = ceil(strlen($data[$key]['#text']) / 100);
        if ($rows < 3) {
          $rows = 3;
        } elseif ($rows > 15) {
          $rows = 15;
        }
        if (!empty($data[$key]['#format']) && \Drupal::config('tmgmt.settings')->get('respect_text_format') == '1' && !$form_state->has('accept_item')) {
          $form[$target_key]['translation'] = array(
            '#type' => 'text_format',
            '#default_value' => isset($data[$key]['#translation']['#text']) ? $data[$key]['#translation']['#text'] : NULL,
            '#title' => t('Translation'),
            '#disabled' => $job_item->isAccepted(),
            '#rows' => $rows,
            '#allowed_formats' => array($data[$key]['#format']),
          );
        }
        elseif ($form_state->has('accept_item')) {
          $form[$target_key]['translation'] = array(
            '#type' => 'textarea',
            '#title' => t('Translation'),
            '#value' => t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'),
            '#disabled' => TRUE,
            '#rows' => $rows,
          );
        }
        else {
          $form[$target_key]['translation'] = array(
            '#type' => 'textarea',
            '#default_value' => isset($data[$key]['#translation']['#text']) ? $data[$key]['#translation']['#text'] : NULL,
            '#title' => t('Translation'),
            '#disabled' => $job_item->isAccepted(),
            '#rows' => $rows,
          );
          if (!empty($data[$key]['#max_length'])) {
            $form[$target_key]['translation']['#max_length'] = $data[$key]['#max_length'];
            $form[$target_key]['translation']['#element_validate'] = ['::validateMaxLength'];
          }
        }

        if (!empty($data[$key]['#format']) && \Drupal::config('tmgmt.settings')->get('respect_text_format') == '1' && !$form_state->has('accept_item')) {
          $form[$target_key]['source'] = array(
            '#type' => 'text_format',
            '#default_value' => $data[$key]['#text'],
            '#title' => t('Source'),
            '#disabled' => TRUE,
            '#rows' => $rows,
            '#allowed_formats' => array($data[$key]['#format']),
          );
        }
        elseif ($form_state->has('accept_item')) {
          $form[$target_key]['source'] = array(
            '#type' => 'textarea',
            '#title' => t('Source'),
            '#value' => t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'),
            '#disabled' => TRUE,
            '#rows' => $rows,
          );
        }
        else {
          $form[$target_key]['source'] = array(
            '#type' => 'textarea',
            '#default_value' => $data[$key]['#text'],
            '#title' => t('Source'),
            '#disabled' => TRUE,
            '#rows' => $rows,
          );
        }

        // Check for source changes and offer actions.
        if (isset($form_state->get('source_changed')[$target_key])) {
          // Show diff if requested.
          if ($form_state->get('show_diff:' . $key)) {
            $keys = \Drupal::service('tmgmt.data')->ensureArrayKey($target_key);

            try {
              $new_data = \Drupal::service('tmgmt.data')->flatten($job_item->getSourceData());
            }
            catch (TMGMTException $e) {
              $new_data = [];
            }

            $current_data = $job_item->getData($keys);

            $diff_header = ['', t('Current text'), '', t('New text')];

            $current_lines = explode("\n", $current_data['#text']);
            $new_lines = explode("\n", isset($new_data[$key]) ? $new_data[$key]['#text'] : '');

            $diff_formatter = new DiffFormatter($this->configFactory());
            $diff = new Diff($current_lines, $new_lines);

            $diff_rows = $diff_formatter->format($diff);
            // Unset start block.
            unset($diff_rows[0]);

            $form[$target_key]['source_changed']['diff'] = [
              '#type' => 'table',
              '#header' => $diff_header,
              '#rows' => $diff_rows,
              '#empty' => $this->t('No visible changes'),
              '#attributes' => [
                'class' => ['diff'],
              ],
            ];
            $form[$target_key]['source_changed']['#attached']['library'][] = 'system/diff';
            $form[$target_key]['diff_actions'] = [
              '#type' => 'container',
            ];
            $form[$target_key]['diff_actions']['resolve-diff'] = [
              '#type' => 'submit',
              '#value' => t('Resolve'),
              '#attributes' => ['title' => t('Apply the changes of the source.')],
              '#name' => 'resolve-diff-' . $target_key,
              '#data_item_key' => $key,
              '#submit' => ['::resolveDiff'],
              '#ajax' => [
                'callback' => '::ajaxReviewForm',
                'wrapper' => $form['#ajaxid'],
              ],
            ];
          }
          else {
            // Display changed message.
            $form[$target_key]['source_changed'] = [
              '#title' => t('Source changed'),
              '#type' => 'item',
              '#value' => $form_state->get('source_changed')[$target_key],
            ];
            $form[$target_key]['source_changed']['message'] = [
              '#title' => t('Source changed'),
              '#type' => 'item',
              '#value' => $form_state->get('source_changed')[$target_key],
            ];
            if (!isset($form_state->get('source_removed')[$target_key])) {
              // Offer resolve action.
              $form[$target_key]['source_changed']['diff_button'] = [
                '#type' => 'submit',
                '#value' => t('Show change'),
                '#name' => 'diff-button-' . $target_key,
                '#data_item_key' => $key,
                '#submit' => ['::showDiff'],
                '#ajax' => [
                  'callback' => '::ajaxReviewForm',
                  'wrapper' => $form['#ajaxid'],
                ],
              ];
            }
          }
        }

        if (isset($form_state->get('validation_messages')[$target_key])) {
          $form[$target_key]['validation_message'] = array(
            '#title' => t('Validation message'),
            '#type' => 'item',
            '#value' => htmlspecialchars($form_state->get('validation_messages')[$target_key]),
          );
        }

        // Give the translator UI controller a chance to affect the data item element.
        if ($job_item->hasTranslator()) {
          $form[$target_key] = \Drupal::service('plugin.manager.tmgmt.translator')
            ->createUIInstance($job_item->getTranslator()->getPluginId())
            ->reviewDataItemElement($form[$target_key], $form_state, $key, $parent_key, $data[$key], $job_item);
          // Give the source ui controller a chance to affect the data item element.
          $form[$target_key] = \Drupal::service('plugin.manager.tmgmt.source')
            ->createUIInstance($job_item->getPlugin())
            ->reviewDataItemElement($form[$target_key], $form_state, $key, $parent_key, $data[$key], $job_item);
        }
      }
    }
    return $form;
  }

  /**
   * Builds the render array for the status icon.
   *
   * @param int $status
   *   Data item status.
   *
   * @return array
   *   The render array for the status icon.
   */
  protected function buildStatusRenderArray($status) {
    $classes = array();
    $classes[] = 'tmgmt-ui-icon';
    // Icon size 32px square.
    $classes[] = 'tmgmt-ui-icon-32';
    switch ($status) {
      case TMGMT_DATA_ITEM_STATE_ACCEPTED:
        $title = t('Accepted');
        $icon = 'core/misc/icons/73b355/check.svg';
        break;
      case TMGMT_DATA_ITEM_STATE_REVIEWED:
        $title = t('Reviewed');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/gray-check.svg';
        break;
      case TMGMT_DATA_ITEM_STATE_TRANSLATED:
        $title = t('Translated');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/ready.svg';
        break;
      case TMGMT_DATA_ITEM_STATE_PENDING:
      default:
        $title = t('Pending');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/hourglass.svg';
        break;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => $classes],
      'icon' => [
        '#theme' => 'image',
        '#uri' => $icon,
        '#title' => $title,
        '#alt' => $title,
      ],
    ];
  }

  /**
   * Ajax callback for the job item review form.
   */
  function ajaxReviewForm(array $form, FormStateInterface $form_state) {
    $key = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, 2);
    $render_data = NestedArray::getValue($form, $key);
    tmgmt_write_request_messages($form_state->getFormObject()->getEntity()->getJob());
    return $render_data;
  }

  /**
   * Submit handler for the HTML tag validation.
   */
  function validateTags(array $form, FormStateInterface $form_state) {
    $validation_messages = array();
    $field_count = 0;
    foreach ($form_state->getValues() as $field => $value) {
      if (is_array($value) && isset($value['translation'])) {
        if (!empty($value['translation'])) {
          $tags_validated = $this->compareHTMLTags($value['source'], $value['translation']);
          if ($tags_validated) {
            $validation_messages[$field] = $tags_validated;
            $field_count++;
          }
        }
      }
    }
    if($field_count > 0){
      drupal_set_message(t('HTML tag validation failed for @count field(s).', array('@count' => $field_count)), 'error');
    }
    else {
      drupal_set_message(t('Validation completed successfully.'));
    }
    $form_state->set('validation_messages', $validation_messages);
    $request = \Drupal::request();
    $url = $this->entity->urlInfo('canonical');
    if ($request->query->has('destination')) {
      $destination = $request->query->get('destination');
      $request->query->remove('destination');
      $url->setOption('query', array('destination' => $destination));
    }
    $form_state->setRedirectUrl($url);
    $form_state->setRebuild();
  }

  /**
   * Submit rebuild.
   */
  function submitRebuild(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Compare the HTML tags of source and translation.
   * @param string $source
   *  Source text.
   * @param string $translation
   *  Translated text.
   */
  function compareHTMLTags($source, $translation) {
    $pattern = "/\<(.*?)\>/";
    if (is_array($source) && isset($source['value'])) {
      $source = $source['value'];
    }
    if (is_array($translation) && isset($translation['value'])) {
      $translation = $translation['value'];
    }
    preg_match_all($pattern, $source, $source_tags);
    preg_match_all($pattern, $translation, $translation_tags);
    $message = '';
    if ($source_tags != $translation_tags) {
      if (count($source_tags[0]) == count($translation_tags[0])) {
        $message .= 'Order of the HTML tags are incorrect. ';
      }
      else {
        $tags = implode(',', array_diff($source_tags[0], $translation_tags[0]));
        if (!empty($tags)) {
          $message .= 'Expected tags ' . $tags . ' not found. ';
        }
        $source_tags_count = $this->htmlTagCount($source_tags[0]);
        $translation_tags_count = $this->htmlTagCount($translation_tags[0]);
        $difference = array_diff_assoc($source_tags_count, $translation_tags_count);
        foreach ($difference as $tag => $count) {
          if (!isset($translation_tags_count[$tag])) {
            $translation_tags_count[$tag] = 0;
          }
          $message .= $tag . ' expected ' . $count . ', found ' . $translation_tags_count[$tag] . '.';
        }
        $unexpected_tags = array_diff_key($translation_tags_count, $source_tags_count);
        foreach ($unexpected_tags as $tag => $count) {
          if (!isset($translation_tags_count[$tag])) {
            $translation_tags_count[$tag] = 0;
          }
          $message .= $count . ' unexpected ' . $tag . ' tag(s), found.';
        }
      }

    }
    return $message;
  }

  /**
   * Compare the HTML tags of source and translation.
   * @param array $tags
   *  array containing all the HTML tags.
   */
  function htmlTagCount($tags) {
    $counted_tags = array();
    foreach ($tags as $tag) {
      if (in_array($tag, array_keys($counted_tags))) {
        $counted_tags[$tag]++;
      }
      else {
        $counted_tags[$tag] = 1;
      }
    }
    return $counted_tags;
  }

  /**
   * Detect source changes and persist on $form_state.
   *
   * @param array $data
   *   The data items.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function trackChangedSource(array $data, FormStateInterface $form_state) {
    $item = $this->entity;
    $source_changed = [];
    $source_removed = [];
    foreach ($data as $target_key => $value) {
      if (is_array($value) && isset($value['#translate']) && $value['#translate']) {
        $field = str_replace('][', '|', $target_key);
        $key = \Drupal::service('tmgmt.data')->ensureArrayKey($field);
        try {
          $new_data = \Drupal::service('tmgmt.data')->flatten($item->getSourceData());
        }
        catch (TMGMTException $e) {
          drupal_set_message(t('The source does not exist any more.'), 'error');
          return;
        }
        $current_data = $item->getData($key);
        if (!isset($new_data[$target_key])) {
          $source_changed[$field] = t('This data item has been removed from the source.');
          $source_removed[$field] = TRUE;
        }
        elseif ($current_data['#text'] != $new_data[$target_key]['#text']) {
          $source_changed[$field] = t('The source has changed.');
        }
      }
    }
    $form_state->set('source_changed', $source_changed);
    $form_state->set('source_removed', $source_removed);
  }

  /**
   * Submit handler to show the diff table of the source.
   */
  public function showDiff(array $form, FormStateInterface $form_state) {
    $target_key = $form_state->getTriggeringElement()['#data_item_key'];
    $form_state->set('show_diff:' . $target_key, TRUE);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to resolve the diff updating the Job Item source.
   */
  public function resolveDiff(array $form, FormStateInterface $form_state) {
    $item = $this->entity;
    $target_key = $form_state->getTriggeringElement()['#data_item_key'];
    $tmgmt_data_item = str_replace('][', '|', $target_key);
    $key = \Drupal::service('tmgmt.data')->ensureArrayKey($tmgmt_data_item);
    $first_key = reset($key);
    $source_data = $item->getSourceData();
    $new_data = \Drupal::service('tmgmt.data')->flatten($source_data)[$target_key];
    $item->updateData($key, $new_data);
    if (isset($source_data[$first_key]['#label'])) {
      $item->addMessage('The conflict in the data item source "@data_item" has been resolved.', ['@data_item' => $source_data[$first_key]['#label']]);
    }
    else {
      $item->addMessage('The conflict in the data item source has been resolved.');
    }
    $item->save();
    $form_state->set('show_diff:' . $target_key, FALSE);
    $form_state->setRebuild();
  }

}
