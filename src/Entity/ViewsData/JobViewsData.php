<?php
/**
 * @file
 * Contains \Drupal\tmgmt\Entity\ViewsData\JobViewsData.
 */

namespace Drupal\tmgmt\Entity\ViewsData;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the job item entity type.
 */
class JobViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['tmgmt_job']['progress'] = array(
      'title' => 'Progress',
      'help' => 'Displays the progress of a job.',
      'real field' => 'tjid',
      'field' => array(
        'id' => 'tmgmt_progress',
      ),
    );
    $data['tmgmt_job']['word_count'] = array(
      'title' => 'Word count',
      'help' => 'Displays the word count of a job.',
      'real field' => 'tjid',
      'field' => array(
        'id' => 'tmgmt_wordcount',
      ),
      'sort' => array(),
    );
    $data['tmgmt_job']['tags_count'] = array(
      'title' => 'Tags count',
      'help' => 'Displays the HTML tags count of a job.',
      'real field' => 'tjid',
      'field' => array(
        'id' => 'tmgmt_tagscount',
      ),
    );
    $data['tmgmt_job']['label'] = array(
      'title' => 'Label',
      'help' => 'Displays a label of the job item.',
      'real field' => 'tjid',
      'field' => array(
        'id' => 'tmgmt_entity_label',
      ),
      'sort' => array(),
    );
    $data['tmgmt_job']['translator']['field']['id'] = 'tmgmt_translator';
    $data['tmgmt_job']['translator']['field']['options callback'] = 'tmgmt_translator_labels';
    $data['tmgmt_job']['translator']['filter']['id'] = 'in_operator';
    $data['tmgmt_job']['translator']['filter']['options callback'] = 'tmgmt_translator_labels';

    return $data;
  }

}
