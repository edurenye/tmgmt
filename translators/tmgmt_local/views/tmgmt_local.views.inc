<?php

/**
 * @file
 * Contains Views controllers for the translation management local task module.
 */

/**
 * Views controller class for the local task entity.
 */
class TMGMTLocalTaskViewsController extends EntityDefaultViewsController {

  /**
   * Overrides EntityDefaultViewsController::views_data().
   */
  public function views_data() {
    $data = parent::views_data();
    $data['tmgmt_local_task']['operations'] = array(
      'title' => t('Operations'),
      'help' => t('Displays a list of edit operations which are available for a task.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_operations',
      ),
    );
    $data['tmgmt_local_task']['progress'] = array(
      'title' => t('Progress'),
      'help' => t('Displays the progress of a job.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_progress',
      ),
    );
    $data['tmgmt_local_task']['word_count'] = array(
      'title' => t('Word count'),
      'help' => t('Displays the word count of a job.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_wordcount',
      ),
    );
    $data['tmgmt_local_task']['item_count'] = array(
      'title' => t('Job item count'),
      'help' => t('Show the amount of job items per task (per job item status)'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_job_item_count',
      ),
    );
    $data['tmgmt_local_task']['assign_to_me'] = array(
      'title' => t('Assign to me'),
      'help' => t('Assign translation task to current user link.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_assign_to_me',
      ),
    );
    $data['tmgmt_local_task']['translate'] = array(
      'title' => t('Translate task items'),
      'help' => t('Translate task items link.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_translate',
      ),
    );
    // Manager handlers.
    $data['tmgmt_local_task']['assign'] = array(
      'title' => t('Assign'),
      'help' => t('Assign translation task to specific translator.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_assign',
      ),
    );
    $data['tmgmt_local_task']['view'] = array(
      'title' => t('Manager View'),
      'help' => t('Manager view for translation task.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_view',
      ),
    );
    $data['tmgmt_job']['task'] = array(
      'title' => t('Translation task'),
      'help' => t('Get the translation task of the job'),
      'relationship' => array(
        'base' => 'tmgmt_local_task',
        'base field' => 'tjid',
        'real field' => 'tjid',
        'label' => t('Job'),
      ),
    );
    $data['tmgmt_local_task']['translate_task_item'] = array(
      'title' => t('Translate Task Item'),
      'help' => t('Display a link for translating translation task item.'),
      'real field' => 'tltid',
      'field' => array(
        'handler' => 'tmgmt_local_task_handler_field_translate_task_item',
      ),
    );

    return $data;
  }

}