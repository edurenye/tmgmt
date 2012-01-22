<?php

/**
 * Implements hook_views_data_alter().
 */
function tmgmt_node_views_data_alter(&$data) {
  // Relationships between job items and nodes.
  $data['tmgmt_job_item']['job_item_to_node'] = array(
    'title' => t('Content'),
    'help' => t('Content that is associated with this job item.'),
    'real field' => 'item_id',
    'relationship' => array(
      'label' => t('Content'),
      'base' => 'node',
      'base field' => 'vid',
      'relationship field' => 'item_id',
      'extra' => array(
        array(
          'table' => 'tmgmt_job_item',
          'field' => 'item_type',
          'operator' => '=',
          'value' => 'node',
        ),
        array(
          'table' => 'tmgmt_job_item',
          'field' => 'source_plugin',
          'operator' => '=',
          'value' => 'node',
        ),
      ),
    ),
  );
  $data['node']['node_to_job_item'] = array(
    'title' => t('Translation job item'),
    'help' => t('Job items of this node.'),
    'relationship' => array(
      'real field' => 'vid',
      'label' => t('Translation job item'),
      'base' => 'tmgmt_job_item',
      'base field' => 'item_id',
      'extra' => array(
        array(
          'field' => 'item_type',
          'operator' => '=',
          'value' => 'node',
        ),
        array(
          'field' => 'source_plugin',
          'operator' => '=',
          'value' => 'node',
        ),
      ),
    ),
  );

  // @TODO: What about a translation managment group?

  $data['node']['tmgmt_translatable_types'] = array(
    'title' => t('Translatable types'),
    'help' => t('Display only nodes from node types which are translatable'),
    'filter' => array(
      'handler' => 'tmgmt_node_ui_handler_filter_node_translatable_types',
      'real field' => 'type',
    ),
  );

  $data['node']['tmgmt_jobs'] = array(
    'title' => t('Translation jobs'),
    'help' => t('Shows all translation jobs which contains this node'),
    'field' => array(
      'handler' => 'tmgmt_node_ui_handler_field_jobs',
      'real field' => 'nid',
    ),
  );
  $data['node']['tmgmt_job_item'] = array(
    'title' => t('Job item'),
    'real field' => 'vid',
    'relationship' => array(
      'title' => t('Translation job item'),
      'label' => t('Translation job item'),
      'base' => 'tmgmt_job_item',
      'base field' => 'item_id',
      'extra' => array(
        array(
          'field' => 'item_type',
          'operator' => '=',
          'value' => 'node',
        ),
        array(
          'field' => 'source_plugin',
          'operator' => '=',
          'value' => 'node',
        ),
      ),
    ),
  );
  $data['node']['translation_language_status'] = array(
    'title' => t('All translation languages'),
    'help' => t('Display all target lanuages.'),
    'field' => array(
      'handler' => 'tmgmt_node_handler_field_translation_language_status',
    ),
  );

  $data['node']['tmgmt_translatable_types'] = array(
    'title' => t('Translatable types'),
    'help' => t('Display only nodes from node types which are translatable'),
    'filter' => array(
      'handler' => 'tmgmt_node_handler_filter_node_translatable_types',
      'real field' => 'type',
    ),
  );
  return $data;
}