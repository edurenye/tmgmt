<?php

/**
 * @file
 * Contains \Drupal\tmgmt_local\Form\AssignMultiple.
 */

namespace Drupal\tmgmt_local\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a node deletion confirmation form.
 */
class AssignMultiple extends FormBase {

  /**
   * The array of tasks to assign.
   *
   * @var string[]
   */
  protected $taskInfo = array();

  /**
   * The tempstore factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The task storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $manager;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   *   The entity manager.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityManagerInterface $manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->storage = $manager->getStorage('tmgmt_local_task');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'task_multiple_assign';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->taskInfo = $this->tempStoreFactory->get('task_multiple_assign')->get(\Drupal::currentUser()->id());
    $form_state->set('tasks', array_keys($this->taskInfo));

    $roles = tmgmt_local_translator_roles();
    if (empty($roles)) {
      drupal_set_message(t('No user role has the "provide translation services" permission. <a href="@url">Configure permissions</a> for the Drupal user module.',
        array('@url' => URL::fromRoute('user.admin_permissions'))), 'warning');
    }

    $form['tuid'] = array(
      '#title' => t('Assign to'),
      '#type' => 'select',
      '#empty_option' => t('- Select user -'),
      '#options' => tmgmt_local_get_assignees_for_tasks($form_state->get('tasks')),
      '#required' => TRUE,
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Assign tasks'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var User $assignee */
    $assignee = User::load($form_state->getValue('tuid'));

    $how_many = 0;
    foreach ($form_state->get('tasks') as $task_id) {
      $task = tmgmt_local_task_load($task_id);
      if ($task) {
        $task->assign($assignee);
        $task->save();
        ++$how_many;
      }
    }

    drupal_set_message(t('Assigned @how_many to user @assignee_name.', array('@how_many' => $how_many, '@assignee_name' => $assignee->getAccountName())));

    $view = Views::getView('tmgmt_local_task_overview');
    $view->initDisplay();
    $form_state->setRedirect($view->getUrl()->getRouteName());
  }

}
