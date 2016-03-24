<?php

namespace Drupal\tmgmt_local\Tests;

use Drupal\tmgmt\Tests\EntityTestBase;

/**
 * Base class for local translator tests.
 */
abstract class LocalTranslatorTestBase extends EntityTestBase {

  /**
   * Translator user.
   *
   * @var object
   */
  protected $assignee;

  protected $localTranslatorPermissions = array(
    'provide translation services',
  );

  protected $localManagerPermissions = [
    'administer translation tasks',
    'provide translation services',
    'view the administration theme',
    'administer themes',
  ];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'tmgmt',
    'tmgmt_language_combination',
    'tmgmt_local',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->loginAsAdmin();
    $this->addLanguage('de');
    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Asserts task status icon.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param string $view
   *   The view where we want to assert.
   * @param string $overview
   *   The overview table to check.
   * @param int $state
   *   The expected state.
   */
  protected function assertTaskStatusIcon($row, $view, $overview, $state) {
    $result = $this->xpath('//*[@id="views-form-tmgmt-local-' . $view . '-' . $overview . '"]/table/tbody/tr[' . $row . ']/td[2]/img')[0];
    $this->assertEqual($result['title'], $state);
  }

  /**
   * Asserts task item status icon.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   */
  protected function assertTaskItemStatusIcon($row, $state) {
    $result = $this->xpath('//*[@id="edit-items"]/div/div/table/tbody/tr[' . $row . ']/td[1]/img')[0];
    $this->assertEqual($result['title'], $state);
  }

  /**
   * Asserts the task progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param string $overview
   *   The overview to be checked.
   * @param int $untranslated
   *   The amount of untranslated items.
   * @param int $translated
   *   The amount of translated items.
   * @param int $completed
   *   The amount of completed items.
   */
  protected function assertTaskProgress($row, $overview, $untranslated, $translated, $completed) {
    $result = $this->xpath('//*[@id="views-form-tmgmt-local-task-overview-' . $overview . '"]/table/tbody/tr[' . $row . ']/td[3]')[0];
    $div_number = 0;
    if ($untranslated > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-untranslated');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-untranslated');
    }
    if ($translated > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-translated');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-translated');
    }
    if ($completed > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-completed');
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-completed');
    }
    $title = t('Untranslated: @untranslated, translated: @translated, completed: @completed.', array(
      '@untranslated' => $untranslated,
      '@translated' => $translated,
      '@completed' => $completed,
    ));
    $this->assertEqual($result->div['title'], $title);
  }

  /**
   * Asserts the task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $untranslated
   *   The amount of untranslated items.
   * @param int $translated
   *   The amount of translated items.
   * @param int $completed
   *   The amount of completed items.
   */
  protected function assertTaskItemProgress($row, $untranslated, $translated, $completed) {
    $result = $this->xpath('//*[@id="edit-items"]/div/div/table/tbody/tr[' . $row . ']/td[2]')[0];
    $div_number = 0;
    if ($untranslated > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-untranslated');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-untranslated');
    }
    if ($translated > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-translated');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-translated');
    }
    if ($completed > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-completed');
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-local-progress-completed');
    }
    $title = t('Untranslated: @untranslated, translated: @translated, completed: @completed.', array(
      '@untranslated' => $untranslated,
      '@translated' => $translated,
      '@completed' => $completed,
    ));
    $this->assertEqual($result->div['title'], $title);
  }

}
