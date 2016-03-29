<?php

/**
 * @file
 * Contains Drupal\tmgmt_content\EntitySourcePluginController.
 */

namespace Drupal\tmgmt_content\Plugin\tmgmt\Source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\SourcePluginBase;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt\ContinuousSourceInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\Entity\Job;

/**
 * Content entity source plugin controller.
 *
 * @SourcePlugin(
 *   id = "content",
 *   label = @Translation("Content Entity"),
 *   description = @Translation("Source handler for entities."),
 *   ui = "Drupal\tmgmt_content\ContentEntitySourcePluginUi"
 * )
 */
class ContentEntitySource extends SourcePluginBase implements SourcePreviewInterface, ContinuousSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel(JobItemInterface $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      return $entity->label() ?: $entity->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(JobItemInterface $job_item) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($entity = \Drupal::entityTypeManager()->getStorage($job_item->getItemType())->load($job_item->getItemId())) {
      if ($entity->hasLinkTemplate('canonical')) {
        $anonymous = new AnonymousUserSession();
        $url = $entity->toUrl();
        $anonymous_access = \Drupal::config('tmgmt.settings')->get('anonymous_access');
        if ($url && $anonymous_access && !$entity->access('view', $anonymous)) {
          $url->setOption('query', [
            'key' => \Drupal::service('tmgmt_content.key_access')
              ->getKey($job_item),
          ]);
        }
        return $url;
      }
    }
    return NULL;
  }

  /**
   * Implements TMGMTEntitySourcePluginController::getData().
   *
   * Returns the data from the fields as a structure that can be processed by
   * the Translation Management system.
   */
  public function getData(JobItemInterface $job_item) {
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    if (!$entity) {
      throw new TMGMTException(t('Unable to load entity %type with id %id', array('%type' => $job_item->getItemType(), '%id' => $job_item->getItemId())));
    }
    $languages = \Drupal::languageManager()->getLanguages();
    $id = $entity->language()->getId();
    if (!isset($languages[$id])) {
      throw new TMGMTException(t('Entity %entity could not be translated because the language %language is not applicable', array('%entity' => $entity->language()->getId(), '%language' => $entity->language()->getName())));
    }

    if (!$entity->hasTranslation($job_item->getJob()->getSourceLangcode())) {
      throw new TMGMTException(t('The entity %id with translation %lang does not exist.', array('%id' => $entity->id(), '%lang' => $job_item->getJob()->getSourceLangcode())));
    }

    $translation = $entity->getTranslation($job_item->getJob()->getSourceLangcode());
    $data = $this->extractTranslatableData($translation);
    $entity_form_display = entity_get_form_display($job_item->getItemType(), $entity->bundle(), 'default');
    uksort($data, function ($a, $b) use ($entity_form_display) {
      $a_weight = NULL;
      $b_weight = NULL;
      // Get the weights.
      if ($entity_form_display->getComponent($a) && !is_null($entity_form_display->getComponent($a)['weight'])) {
        $a_weight = (int) $entity_form_display->getComponent($a)['weight'];
      }
      if ($entity_form_display->getComponent($b) && !is_null($entity_form_display->getComponent($b)['weight'])) {
        $b_weight = (int) $entity_form_display->getComponent($b)['weight'];
      }

      // If neither field has a weight, sort alphabetically.
      if ($a_weight === NULL && $b_weight === NULL) {
        return ($a > $b) ? 1 : -1;
      }
      // If one of them has no weight, the other comes first.
      elseif ($a_weight === NULL) {
        return 1;
      }
      elseif ($b_weight === NULL) {
        return -1;
      }
      // If both have a weight, sort by weight.
      elseif ($a_weight == $b_weight) {
        return 0;
      }
      else {
        return ($a_weight > $b_weight) ? 1 : -1;
      }
    });
    return $data;
  }

  /**
   * Extracts translatable data from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the translatable data from.
   *
   * @return array $data
   *   Translatable data.
   */
  public function extractTranslatableData(ContentEntityInterface $entity) {

    // @todo Expand this list or find a better solution to exclude fields like
    //   content_translation_source.

    $field_definitions = $entity->getFieldDefinitions();
    $exclude_field_types = ['language'];
    $translatable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($exclude_field_types) {
        return $field_definition->isTranslatable() && !in_array($field_definition->getType(), $exclude_field_types);
    });

    $data = array();
    foreach ($translatable_fields as $key => $field_definition) {
      $field = $entity->get($key);
      foreach ($field as $index => $field_item) {
        $format = NULL;
        $translatable_properties = 0;
        /* @var FieldItemInterface $field_item */
        foreach ($field_item->getProperties() as $property_key => $property) {
          // Ignore computed values.
          $property_definition = $property->getDataDefinition();
          // Ignore values that are not primitives.
          if (!($property instanceof PrimitiveInterface)) {
            continue;
          }
          $translate = TRUE;
          // Ignore properties with limited allowed values or if they're not strings.
          if ($property instanceof OptionsProviderInterface || !($property instanceof StringInterface)) {
            $translate = FALSE;
          }
          // All the labels are here, to make sure we don't have empty labels in
          // the UI because of no data.
          if ($translate == TRUE) {
            $data[$key]['#label'] = $field_definition->getLabel();
            if (count($field) > 1) {
              // More than one item, add a label for the delta.
              $data[$key][$index]['#label'] = t('Delta #@delta', array('@delta' => $index));
            }
          }
          $data[$key][$index][$property_key] = array(
            '#label' => $property_definition->getLabel(),
            '#text' => $property->getValue(),
            '#translate' => $translate,
          );

          $translatable_properties += (int) $translate;
          if ($translate && ($field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('max_length') != 0)) {
            $data[$key][$index][$property_key]['#max_length'] = $field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('max_length');
          }

          if ($property_definition->getDataType() == 'filter_format') {
            $format = $property->getValue();
          }
        }
        // Add the format to the translatable properties.
        if (!empty($format)) {
          foreach ($data[$key][$index] as $name => $value) {
            if (is_array($value) && isset($value['#translate']) && $value['#translate'] == TRUE) {
              $data[$key][$index][$name]['#format'] = $format;
            }
          }
        }
        // If there is only one translatable property, remove the label for it.
        if ($translatable_properties <= 1) {
          foreach (Element::children($data[$key][$index]) as $property_key) {
            unset($data[$key][$index][$property_key]['#label']);
          }
        }
      }
    }

    $embeddable_fields = $this->getEmbeddableFields($entity);
    foreach ($embeddable_fields as $key => $field_definition) {
      $field = $entity->get($key);
      foreach ($field as $index => $field_item) {
        /* @var FieldItemInterface $field_item */
        foreach ($field_item->getProperties(TRUE) as $property_key => $property) {
          // If the property is a content entity reference and it's value is
          // defined, than we call this method again to get all the data.
          if ($property instanceof EntityReference && $property->getValue() instanceof ContentEntityInterface) {
            // All the labels are here, to make sure we don't have empty
            // labels in the UI because of no data.
            $data[$key]['#label'] = $field_definition->getLabel();
            if (count($field) > 1) {
              // More than one item, add a label for the delta.
              $data[$key][$index]['#label'] = t('Delta #@delta', array('@delta' => $index));
            }
            $data[$key][$index][$property_key] = $this->extractTranslatableData($property->getValue());
          }
        }

      }
    }
    return $data;
  }

  /**
   * Returns fields that should be embedded into the data for the given entity.
   *
   * Includes explicitly enabled fields and composite entities that are
   * implicitly included to the translatable data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the translatable data from.
   *
   * @return array $embeddable_fields
   *   Translatable data.
   */
  public function getEmbeddableFields(ContentEntityInterface $entity) {
    // Get the configurable embeddable references.
    $field_definitions = $entity->getFieldDefinitions();
    $embeddable_field_names = \Drupal::config('tmgmt_content.settings')->get('embedded_fields');
    $embeddable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($embeddable_field_names) {
      return !$field_definition->isTranslatable() && isset($embeddable_field_names[$field_definition->getTargetEntityTypeId()][$field_definition->getName()]);
    });

    // Get always embedded references.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    foreach ($field_definitions as $field_name => $field_definition) {
      $storage_definition = $field_definition->getFieldStorageDefinition();

      $property_definitions = $storage_definition->getPropertyDefinitions();
      foreach ($property_definitions as $property_definition) {
        // Look for entity_reference properties where the storage definition
        // has a target type setting and that is enabled for content
        // translation.
        if (in_array($property_definition->getDataType(), ['entity_reference', 'entity_revision_reference']) && $storage_definition->getSetting('target_type') && $content_translation_manager->isEnabled($storage_definition->getSetting('target_type'))) {
          // Include field if the target entity has the parent type field key
          // set, which is defined by entity_reference_revisions.
          $target_entity_type = \Drupal::entityTypeManager()->getDefinition($storage_definition->getSetting('target_type'));
          if ($target_entity_type->get('entity_revision_parent_type_field')) {
            $embeddable_fields[$field_name] = $field_definition;
          }
        }
      }
    }

    return $embeddable_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTranslation(JobItemInterface $job_item, $target_langcode) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    if (!$entity) {
      $job_item->addMessage('The entity %id of type %type does not exist, the job can not be completed.', array(
        '%id' => $job_item->getItemId(),
        '%type' => $job_item->getItemType(),
      ), 'error');
      return FALSE;
    }

    $data = $job_item->getData();
    $this->doSaveTranslations($entity, $data, $target_langcode);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypes() {
    $entity_types = \Drupal::entityManager()->getDefinitions();
    $types = array();
    $content_translation_manager = \Drupal::service('content_translation.manager');
    foreach ($entity_types as $entity_type_name => $entity_type) {
      // Entity types with this key set are considered composite entities and
      // always embedded in others. Do not expose them as their own item type.
      if ($entity_type->get('entity_revision_parent_type_field')) {
        continue;
      }
      if ($content_translation_manager->isEnabled($entity_type->id())) {
        $types[$entity_type_name] = $entity_type->getLabel();
      }
    }
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypeLabel($type) {
    return \Drupal::entityManager()->getDefinition($type)->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(JobItemInterface $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      $bundles = entity_get_bundles($job_item->getItemType());
      $entity_type = $entity->getEntityType();
      $bundle = $entity->bundle();
      // Display entity type and label if we have one and the bundle isn't
      // the same as the entity type.
      if (isset($bundles[$bundle]) && $bundle != $job_item->getItemType()) {
        return t('@type (@bundle)', array('@type' => $entity_type->getLabel(), '@bundle' => $bundles[$bundle]['label']));
      }
      // Otherwise just display the entity type label.
      return $entity_type->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLangCode(JobItemInterface $job_item) {
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    return $entity->getUntranslated()->language()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingLangCodes(JobItemInterface $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      return array_keys($entity->getTranslationLanguages());
    }

    return array();
  }

  /**
   * Saves translation data in an entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be saved.
   * @param array $data
   *   The translation data for the fields.
   * @param string $target_langcode
   *   The target language.
   */
  protected function doSaveTranslations(ContentEntityInterface $entity, array $data, $target_langcode) {
    // If the translation for this language does not exist yet, initialize it.
    if (!$entity->hasTranslation($target_langcode)) {
      $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $embeded_fields = \Drupal::config('tmgmt_content.settings')->get('embedded_fields');

    $translation = $entity->getTranslation($target_langcode);
    $manager = \Drupal::service('content_translation.manager');
    $manager->getTranslationMetadata($translation)->setSource($entity->language()->getId());

    foreach ($data as $name => $field_data) {
      foreach (Element::children($field_data) as $delta) {
        $field_item = $field_data[$delta];
        foreach (Element::children($field_item) as $property) {
          $property_data = $field_item[$property];
          // If there is translation data for the field property, save it.
          if (isset($property_data['#translation']['#text']) && $property_data['#translate']) {
            $translation->get($name)
              ->offsetGet($delta)
              ->set($property, $property_data['#translation']['#text']);
          }
          // If the field is an embeddable reference, we assume that the
          // property is a field reference.
          elseif (isset($embeded_fields[$entity->getEntityTypeId()][$name])) {
            $this->doSaveTranslations($translation->get($name)->offsetGet($delta)->$property, $property_data, $target_langcode);
          }
        }
      }
    }
    $translation->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewUrl(JobItemInterface $job_item) {
    if ($job_item->getJob()->isActive() && !($job_item->isAborted() || $job_item->isAccepted())) {
      return new Url('tmgmt_content.job_item_preview', ['tmgmt_job_item' => $job_item->id()], ['query' => ['key' => \Drupal::service('tmgmt_content.key_access')->getKey($job_item)]]);
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function continuousSettingsForm(array &$form, FormStateInterface $form_state, Job $job) {
    $continuous_settings = $job->getContinuousSettings();
    $element = array();
    $item_types = $this->getItemTypes();
    asort($item_types);
    $entity_type_manager = \Drupal::entityTypeManager();
    foreach ($item_types as $item_type => $item_type_label) {
      $entity_type = $entity_type_manager->getDefinition($item_type);
      $element[$entity_type->id()]['enabled'] = array(
        '#type' => 'checkbox',
        '#title' => $item_type_label,
        '#default_value' => isset($continuous_settings[$this->getPluginId()][$entity_type->id()]) ? $continuous_settings[$this->getPluginId()][$entity_type->id()]['enabled'] : FALSE,
      );
      if ($entity_type->hasKey('bundle')) {
        $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($item_type);
        $element[$entity_type->id()]['bundles'] = array(
          '#title' => $this->getBundleLabel($entity_type),
          '#type' => 'details',
          '#open' => TRUE,
          '#states' => array(
            'invisible' => array(
              'input[name="continuous_settings[' . $this->getPluginId() . '][' . $entity_type->id() . '][enabled]"]' => array('checked' => FALSE),
            ),
          ),
        );
        foreach ($bundles as $bundle => $bundle_label) {
          if (\Drupal::service('content_translation.manager')->isEnabled($entity_type->id(), $bundle)) {
            $element[$entity_type->id()]['bundles'][$bundle] = array(
              '#type' => 'checkbox',
              '#title' => $bundle_label['label'],
              '#default_value' => isset($continuous_settings[$this->getPluginId()][$entity_type->id()]['bundles'][$bundle]) ? $continuous_settings[$this->getPluginId()][$entity_type->id()]['bundles'][$bundle] : FALSE,
            );
          }
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateContinuousItem(Job $job, $plugin, $item_type, $item_id) {
    $continuous_settings = $job->getContinuousSettings();
    $entity_manager = \Drupal::entityTypeManager();
    $entity = $entity_manager->getStorage($item_type)->load($item_id);
    $translation_manager = \Drupal::service('content_translation.manager');
    $translation = $entity->hasTranslation($job->getTargetLangcode()) ? $entity->getTranslation($job->getTargetLangcode()) : NULL;
    $metadata = isset($translation) ? $translation_manager->getTranslationMetadata($translation) : NULL;

    // If a translation exists and is not marked as outdated, no new job items
    // needs to be created.
    if (isset($translation) && !$metadata->isOutdated()) {
      return FALSE;
    }
    else {
      if ($entity && $entity->getEntityType()->hasKey('bundle')) {
        // The entity type has bundles, check both the entity type setting and
        // the bundle.
        if (!empty($continuous_settings[$plugin][$item_type]['bundles'][$entity->bundle()]) && !empty($continuous_settings[$plugin][$item_type]['enabled'])) {
          return TRUE;
        }
      }
      // No bundles, only check entity type setting.
      elseif (!empty($continuous_settings[$plugin][$item_type]['enabled'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the bundle label for a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel(EntityTypeInterface $entity_type) {
    if ($entity_type->getBundleLabel()) {
      return $entity_type->getBundleLabel();
    }
    if ($entity_type->getBundleEntityType()) {
      return \Drupal::entityTypeManager()
        ->getDefinition($entity_type->getBundleEntityType())
        ->getLabel();
    }
    return $this->t('@label type', ['@label' => $entity_type->getLabel()]);
  }

}
