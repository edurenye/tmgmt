<?php

/**
 * @file
 * Theme file stub for tmgmt.
 */

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\Core\Render\Element;

/**
 * Generic theme function for use with Render API.
 *
 * Renders the #title and #attributes properties if they are present.
 */
function theme_tmgmt_element($variables) {
  $element = $variables['element'];

  // Use the #title attribute.
  $title = '';
  if (!empty($element['#title'])) {
    $title = '<h3>' . Html::escape($element['#title']) . '</h3>';
  }

  // Use #attributes to customize a wrapper <div>.
  $attributes = '';
  if (!empty($element['#attributes'])) {
    // @todo: Convert the source.
    $attributes = new Attribute(($element['#attributes']));
  }

  // Render any child items.
  if (!$element['#children']) {
    $element['#children'] = drupal_render_children($element);
  }

  // Build simple output.
  $output = "<div{$attributes}>{$title}{$element['#children']}</div>";
  return $output;
}

/**
 * Gets translation language status.
 *
 * @param array $variables
 *   - 'translation_status': A flag that determines the status. Possible values:
 *     original, missing, outofdate.
 *   - 'job_item': Current job item entity associated with translation.
 *
 * @return string
 *   Icon or a link icon that explains the translation status and possibly
 *   links to an active translation job.
 */
function theme_tmgmt_translation_language_status_single($variables) {
  switch ($variables['translation_status']) {
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

  $status = SafeMarkup::format('<img src="@s" title="@l"><span></span></img>', array("@s" => file_create_url($icon), "@l" => $label));

  // If we have an active job item, wrap it in a link.
  if (!empty($variables['job_item'])) {
    $states_labels = JobItem::getStates();
    $state_label = $states_labels[$variables['job_item']->getState()];
    $label = t('Active job item: @state', array('@state' => $state_label));
    $uri = $variables['job_item']->urlInfo();
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = $variables['job_item']->getJob();

    switch ($variables['job_item']->getState()) {
      case JobItem::STATE_ACTIVE:
        if ($job->isUnprocessed()) {
          $uri = $job->urlInfo();
          $label = t('Active job item: @state', array('@state' => $state_label));
        }
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/hourglass.svg';
        break;

      case JobItem::STATE_REVIEW:
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/ready.svg';
        break;
    }

    $job_status = SafeMarkup::format('<img src="@s" title="@l"><span></span></img>', array("@s" => file_create_url($icon), "@l" => $label));

    $uri->setOption('query', array('destination' => Url::fromRoute('<current>')->getInternalPath()));
    $uri->setOption('attributes', array('title' => $label));
    $uri->setOption('html', TRUE);
    $status .= \Drupal::l(['#markup' => $job_status], $uri);
  }
  return $status;
}

/**
 * Renders a data item status as an HTML/CSS icon.
 */
function theme_tmgmt_translator_review_form_element_status($variables) {
  $classes = array();
  $classes[] = 'tmgmt-ui-icon';
  $classes[] = 'tmgmt-ui-icon-32'; // Icon size 32px square.
  switch ($variables['status']['#value']) {
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
  return SafeMarkup::format('<div class="@c"><img src="@s" alt="@t"/><span></span></div>', array("@c" => implode(' ', $classes), "@s" => file_create_url($icon), "@t" => $title));
}

/**
 * Render one single data item as a table row.
 */
function theme_tmgmt_translator_review_form_element($variables) {
  $element = $variables['element'];
  // Label of all element groups.
  if (!isset($element['#top_label'])) {
    $element['#top_label'] = array_shift($element['#parent_label']);
  }
  // Label of the current data item.
  if (!isset($element['#leave_label'])) {
    $element['#leave_label'] = array_pop($element['#parent_label']);
  }
  // Do not repeat labels inside the same hierarchy.
  if ($element['#top_label'] == $element['#leave_label']) {
    $element['#leave_label'] = '';
  }
  $result = '<tr' . new Attribute(array('class' => $element['#zebra'])) . '>';
  $row = array();
  $leaf_label = '<label>' . $element['#leave_label'] . '</label>';
  $leaf_label = '<div class="form-item form-type-label">' . $leaf_label . '</div>';
  $row[] = array(
    'data' => $leaf_label . '<div class="tmgmt-ui-state">' . \Drupal::service('renderer')->render($element['status']) . '</div>',
    'class' => 'tmgmt-ui-data-item-label',
  );
  $row[] = array(
    'data' => \Drupal::service('renderer')->render($element['source']),
    'class' => 'tmgmt-ui-data-item-source',
  );
  $row[] = array(
    'data' => \Drupal::service('renderer')->render($element['translation']),
    'class' => 'tmgmt-ui-data-item-translation',
  );
  $row[] = array(
    'data' => \Drupal::service('renderer')->render($element['actions']),
    'class' => 'tmgmt-ui-data-item-actions',
  );
  foreach ($row as $cell) {
    $result .= tmgmt_build_table_cell($cell);
  }
  $result .= '</tr>';

  if (!empty($element['below'])) {
    $result .= '<tr' . new Attribute(array('class' => $element['#zebra'])) . '>';
    $cell = [
      'data' => \Drupal::service('renderer')->render($element['below']),
      'colspan' => '2',
    ];
    $result .= '<td></td>' . tmgmt_build_table_cell($cell) . '<td></td>';
    $result .= '</tr>';
  }

  return $result;
}

/**
 * Builds a table cell.
 *
 * @param array|string $cell
 *   A cell content array or string.
 *
 * @return string
 *   The table cell.
 */
function tmgmt_build_table_cell($cell) {
  if (!is_array($cell)) {
    $cell_content = $cell;
    $cell_attributes = array();
    $is_header = FALSE;
  }
  else {
    $cell_content = '';
    if (isset($cell['data'])) {
      $cell_content = $cell['data'];
      unset($cell['data']);
    }
    // Flag the cell as a header or not and remove the flag.
    $is_header = !empty($cell['header']);
    unset($cell['header']);

    $cell_attributes = $cell;

    if (is_array($cell_content)) {
      $cell_content = \Drupal::service('renderer')->render($cell_content);
    }
  }
  $cell_tag = $is_header ? 'th' : 'td';
  return '<' . $cell_tag . new Attribute($cell_attributes) . '>' . $cell_content . '</' . $cell_tag . '>';
}

/**
 * Renders a table containing a group of data items belonging to the same field.
 */
function theme_tmgmt_translator_review_form($variables) {
  $element = $variables['element'];
  $result = '';
  $labels = '';
  $parent_label = '';
  $source_changed = '';
  $validation_message = '';
  $element_groups = array();
  $element_group = '';
  foreach (Element::children($element) as $key) {
    // Label of all element groups.
    $parent_label = array_shift($element[$key]['#parent_label']);
    $element[$key]['#top_label'] = $parent_label;
    $element[$key]['#leave_label'] = array_pop($element[$key]['#parent_label']);
    // Start a new element group.
    if ($labels != $element[$key]['#parent_label']) {
      $labels = $element[$key]['#parent_label'];
      if (!empty($labels)) {
        // Append to previous group to the group collection.
        if (!empty($element_group)) {
          $element_groups[] = '<tbody>' . $element_group . '</tbody>';
        }
        // Header row for the current element group.
        $cell = array(
          // @todo: Deal with right to left languages.
          'data' => SafeMarkup::checkPlain(implode(t(' > '), $labels)),
          'colspan' => 4,
          'header' => TRUE,
        );
        $element_group = '<tr>' . tmgmt_build_table_cell($cell, TRUE) . '</tr>';
      }
    }
    if (isset($element[$key]['source_changed'])) {
      if (isset($element[$key]['source_changed']['message'])) {
        $source_changed = '<div class="tmgmt_source_changed"><div class="messages messages--warning"><span class="tmgmt-review-message-inline"><span>' . $element[$key]['source_changed']['message']['#value'] . '</span>' . \Drupal::service('renderer')
            ->render($element[$key]['source_changed']['diff_button']) . '</span></div></div>';
        $diff_actions = '';
      }
      else {
        $source_changed = \Drupal::service('renderer')->render($element[$key]['source_changed']);
        $diff_actions = \Drupal::service('renderer')->render($element[$key]['diff_actions']);
      }
      $source_changed = '<tr><td></td><td colspan="2">' . $source_changed . '</td><td class="tmgmt-ui-data-item-actions">' . $diff_actions . '</td></tr>';
    }
    if (isset($element[$key]['validation_message'])) {
      $validation_message = '<div class="tmgmt_validation_message"> <p class="messages messages--warning">' . $element[$key]['validation_message']['#value'] . '</p> </div>';
    }
    $element_group .= \Drupal::service('renderer')->render($element[$key]);
  }

  // Append the last group to the group collection.
  $element_groups[] = '<tbody>' . $element_group . $source_changed . '</tbody>';

  // Display the label of all element groups inside a table header.
  if (!empty($parent_label)) {
    $cell = array(
      'data' => $parent_label,
      'colspan' => 5,
      'header' => TRUE,
    );
    $result = '<thead><tr>' . tmgmt_build_table_cell($cell) . '</tr></thead>' . implode('', $element_groups);
  }
  $table = '<table class="tmgmt-ui-review"><colgroup width="100" /><colgroup width="*" span="2" /><colgroup width="100" />' . $result . '</table>' . $validation_message;

  return '<div id="' . $element['#ajaxid'] . '">' . $table . '</div>';
}
