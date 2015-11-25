<?php

/**
 * @file
 * Contains \Drupal\tmgmt_local\Tests\LocalTranslatorTest.
 */

namespace Drupal\tmgmt_local\Tests;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\Tests\TMGMTTestBase;
use Drupal\tmgmt_local\Entity\LocalTask;

/**
 * Basic tests for the local translator.
 *
 * @group tmgmt
 */
class LocalTranslatorTest extends TMGMTTestBase {

  /**
   * Translator user.
   *
   * @var object
   */
  protected $local_translator;

  protected $local_translator_permissions = array(
    'provide translation services',
  );

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt_language_combination', 'tmgmt_local');

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
    $this->loginAsAdmin();
    $this->addLanguage('de');
  }

  function testTranslatorSkillsForTasks() {

    $this->addLanguage('fr');

    $translator1 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator1);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator1->id() . '/edit', $edit, t('Save'));

    $translator2 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator2);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'de',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));

    $translator3 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator3);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'de',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[2][language_from]' => 'en',
      'tmgmt_translation_skills[2][language_to]' => 'fr',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));

    $job1 = $this->createJob('en', 'de');
    $job2 = $this->createJob('de', 'en');
    $job3 = $this->createJob('en', 'fr');

    $local_task1 = LocalTask::create(array(
      'uid' => $job1->getOwnerId(),
      'tjid' => $job1->id(),
      'title' => 'Task 1',
    ));
    $local_task1->save();

    $local_task2 = LocalTask::create(array(
      'uid' => $job2->getOwnerId(),
      'tjid' => $job2->id(),
      'title' => 'Task 2',
    ));
    $local_task2->save();

    $local_task3 = LocalTask::create(array(
      'uid' => $job3->getOwnerId(),
      'tjid' => $job3->id(),
      'title' => 'Task 3',
    ));
    $local_task3->save();

    // Test languages involved in tasks.
    $this->assertEqual(
      tmgmt_local_tasks_languages(array($local_task1->id(), $local_task2->id(), $local_task3->id())),
      array(
        'en' => array('de', 'fr'),
        'de' => array('en'),
      )
    );

    // Test available translators for task en - de.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->id())),
      array(
        $translator1->id() => $translator1->getUsername(),
        $translator2->id() => $translator2->getUsername(),
        $translator3->id() => $translator3->getUsername(),
      )
    );

    // Test available translators for tasks en - de, de - en.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->id(), $local_task2->id())),
      array(
        $translator2->id() => $translator2->getUsername(),
        $translator3->id() => $translator3->getUsername(),
      )
    );

    // Test available translators for tasks en - de, de - en, en - fr.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->id(), $local_task2->id(), $local_task3->id())),
      array(
        $translator3->id() => $translator3->getUsername(),
      )
    );
  }

  /**
   * Test the basic translation workflow
   */
  function testBasicWorkflow() {
    $translator = Translator::load('local');

    // Create a job and request a local translation.
    $this->loginAsTranslator();
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->settings->job_comment = $job_comment = 'Dummy job comment';
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');
    $job->save();

    // Make sure that the checkout page works as expected when there are no
    // roles.
    $this->drupalGet($job->getSystemPath());
    $this->assertText(t('@translator can not translate from @source to @target.', array('@translator' => 'Local Translator (auto created)', '@source' => 'English', '@target' => 'German')));
    $this->local_translator = $this->drupalCreateUser($this->local_translator_permissions);

    // The same when there is a single role.
    $this->drupalGet($job->getSystemPath());
    $this->assertText(t('@translator can not translate from @source to @target.', array('@translator' => 'Local Translator (auto created)', '@source' => 'English', '@target' => 'German')));

    // Create another local translator with the required abilities.
    $other_translator_same = $this->drupalCreateUser($this->local_translator_permissions);

    // And test again with two roles but still no abilities.
    $this->drupalGet($job->getSystemPath());
    $this->assertText(t('@translator can not translate from @source to @target.', array('@translator' => 'Local Translator (auto created)', '@source' => 'English', '@target' => 'German')));

    $this->drupalLogin($other_translator_same);
    // Configure language abilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $other_translator_same->id() . '/edit', $edit, t('Save'));

    // Check that the user is not listed in the translator selection form.
    $this->loginAsAdmin();
    $this->drupalGet($job->getSystemPath());
    $this->assertText(t('Select translator for this job'));
    $this->assertText($other_translator_same->getUsername());
    $this->assertNoText($this->local_translator->getUsername());

    $this->drupalLogin($this->local_translator);
    // Configure language abilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $this->local_translator->id() . '/edit', $edit, t('Save'));

    // Check that the translator is now listed.
    $this->loginAsAdmin();
    $this->drupalGet($job->getSystemPath());
    $this->assertText($this->local_translator->getUsername());

    $job->requestTranslation();

    // Test for job comment in the job checkout info pane.
    $this->drupalGet($job->getSystemPath());
    $this->assertText($job_comment);

    $this->drupalLogin($this->local_translator);

    // Create a second local translator with different language abilities,
    // make sure that he does not see the task.
    $other_translator = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($other_translator);
    // Configure language abilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'de',
      'tmgmt_translation_skills[0][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $other_translator->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('translate');
    $this->assertNoText(t('Task for @job', array('@job' => $job->label())));

    $this->drupalLogin($this->local_translator);

    // Check the translate overview.
    $this->drupalGet('translate');
    $this->assertText(t('Task for @job', array('@job' => $job->label())));
    // @todo: Fails, encoding problem?
    //$this->assertText(t('@from => @to', array('@from' => 'en', '@to' => 'de')));
    $edit = array(
      'views_bulk_operations[0]' => $job->id(),
    );
    $this->drupalPostForm(NULL, $edit, t('Assign to me'));
    $this->assertText(t('Performed Assign to me on 1 item.'));

    // Unassign again.
    $edit = array(
      'views_bulk_operations[0]' => $job->id(),
    );
    $this->drupalPostForm(NULL, $edit, t('Unassign'));
    $this->assertText(t('Performed Unassign on 1 item.'));

    // Now test the assign link.
    $this->clickLink(t('assign'));

    // Log in with the translator with the same abilities, make sure that he
    // does not see the assigned task.
    $this->drupalLogin($other_translator_same);
    $this->drupalGet('translate');
    $this->assertNoText(t('Task for @job', array('@job' => $job->label())));

    $this->drupalLogin($this->local_translator);

    // Translate the task.
    $this->drupalGet('translate');
    $this->clickLink(t('view'));

    // Assert created local task and task items.
    $this->assertTrue(preg_match('|translate/(\d+)|', $this->getUrl(), $matches), 'Task found');
    $task = tmgmt_local_task_load($matches[1]);
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 0);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 2);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isPending());
    $this->assertEqual($first_task_item->getCountCompleted(), 0);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 1);

    $this->assertText('test_source:test:1');
    $this->assertText('test_source:test:2');
    $this->assertText(t('Untranslated'));

    // Translate the first item.
    $this->clickLink(t('translate'));

    $this->assertText(t('Dummy'));
    // Job comment is present in the translate tool as well.
    $this->assertText($job_comment);
    $this->assertText('test_source:test:1');

    // Try to complete a translation when translations are missing.
    $this->drupalPostForm(NULL, array(), t('Save as completed'));
    $this->assertText(t('Missing translation.'));

    $edit = array(
      'dummy|deep_nesting[translation]' => $translation1 = 'German translation of source 1',
    );
    $this->drupalPostForm(NULL, $edit, t('Save as completed'));

    // Review and accept the first item.
    \Drupal::entityManager()->getStorage('tmgmt_job_item')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $item1 = JobItem::load(1);
    $item1->acceptTranslation();

    // The first item should be accepted now, the second still in progress.
    $this->drupalGet('translate/1');
    $this->assertText(t('Completed'));
    $this->assertText(t('Untranslated'));

    $task = tmgmt_local_task_load($task->id());
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 1);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 1);

    // Translate the second item but do not mark as translated it yet.
    $this->clickLink(t('translate'));
    $edit = array(
      'dummy|deep_nesting[translation]' => $translation2 = 'German translation of source 2',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // The first item is still completed, the second still untranslated.
    $this->assertText(t('Completed'));
    $this->assertText(t('Untranslated'));

    \Drupal::entityManager()->getStorage('tmgmt_local_task')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->id());
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 1);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 1);

    // Mark the data item as translated but don't save the task item as
    // completed.
    $this->clickLink(t('translate'));
    $this->drupalPostForm(NULL, array(), t('✓'));

    \Drupal::entityManager()->getStorage('tmgmt_local_task')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->id());
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 1);
    $this->assertEqual($task->getCountUntranslated(), 0);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 1);
    $this->assertEqual($second_task_item->getCountUntranslated(), 0);

    // Check the job data.
    \Drupal::entityManager()->getStorage('tmgmt_job')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_job_item')->resetCache();
    $job = Job::load($job->id());
    list($item1, $item2) = array_values($job->getItems());
    // The text in the first item should be available for review, the
    // translation of the second item not.
    $this->assertEqual($item1->getData(array('dummy', 'deep_nesting', '#translation', '#text')), $translation1);
    $this->assertEqual($item2->getData(array('dummy', 'deep_nesting', '#translation', '#text')), '');

    // Check the overview page, the task should still show in progress.
    $this->drupalGet('translate');
    $this->assertText(t('Pending'));

    // Mark the second item as completed now.
    $this->clickLink(t('view'));
    $this->clickLink(t('translate'));
    $this->drupalPostForm(NULL, array(), t('Save as completed'));

    // Review and accept the second item.
    \Drupal::entityManager()->getStorage('tmgmt_job_item')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $item1 = JobItem::load(2);
    $item1->acceptTranslation();

    // Refresh the page.
    $this->drupalGet($this->url);

    $task = tmgmt_local_task_load($task->id());
    $this->assertTrue($task->isClosed());
    $this->assertEqual($task->getCountCompleted(), 2);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 0);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isClosed());
    $this->assertEqual($second_task_item->getCountCompleted(), 1);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 0);

    // We should have been redirect back to the overview, the task should be
    // completed now.
    $this->assertNoText($task->getJob()->label());
    $this->clickLink(t('Closed'));
    $this->assertText($task->getJob()->label());
    $this->assertText(t('Completed'));

    \Drupal::entityManager()->getStorage('tmgmt_job')->resetCache();
    \Drupal::entityManager()->getStorage('tmgmt_job_item')->resetCache();
    $job = Job::load($job->id());
    list($item1, $item2) = array_values($job->getItems());
    // Job was accepted and finished automatically due to the default approve
    // setting.
    $this->assertTrue($job->isFinished());
    $this->assertEqual($item1->getData(array(
      'dummy',
      'deep_nesting',
      '#translation',
      '#text'
    )), $translation1);
    $this->assertEqual($item2->getData(array(
      'dummy',
      'deep_nesting',
      '#translation',
      '#text'
    )), $translation2);

    // Delete the job, make sure that the corresponding task and task items were
    // deleted.
    $job->delete();
    $this->assertFalse(tmgmt_local_task_item_load($task->id()));
    $this->assertFalse($task->getItems());
  }

  /**
   * Test the allow all setting.
   */
  function testAllowAll() {
    $translator = Translator::load('local');

    // Create a job and request a local translation.
    $this->loginAsTranslator();
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    $this->assertFalse($job->requestTranslation(), 'Translation request was denied.');

    // Now enable the setting.
    $translator->setSetting('allow_all', TRUE);
    $translator->save();

    $this->assertIdentical(NULL, $job->requestTranslation(), 'Translation request was successfull');
    $this->assertTrue($job->isActive());
  }

  function testAbilitiesAPI() {

    $this->addLanguage('fr');
    $this->addLanguage('ru');
    $this->addLanguage('it');

    $all_translators = array();

    $translator1 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator1->id()] = $translator1->getUsername();
    $this->drupalLogin($translator1);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator1->id() . '/edit', $edit, t('Save'));

    $translator2 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator2->id()] = $translator2->getUsername();
    $this->drupalLogin($translator2);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'ru',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'en',
      'tmgmt_translation_skills[1][language_to]' => 'fr',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[2][language_from]' => 'fr',
      'tmgmt_translation_skills[2][language_to]' => 'it',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));

    $translator3 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator3->id()] = $translator3->getUsername();
    $this->drupalLogin($translator3);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'fr',
      'tmgmt_translation_skills[0][language_to]' => 'ru',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'it',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));

    // Test target languages.
    $target_languages = tmgmt_local_supported_target_languages('fr');
    $this->assertTrue(isset($target_languages['it']));
    $this->assertTrue(isset($target_languages['ru']));
    $target_languages = tmgmt_local_supported_target_languages('en');
    $this->assertTrue(isset($target_languages['fr']));
    $this->assertTrue(isset($target_languages['ru']));

    // Test language pairs.
    $this->assertEqual(tmgmt_local_supported_language_pairs(), array (
      'en__de' =>
        array(
          'source_language' => 'en',
          'target_language' => 'de',
        ),
      'en__ru' =>
        array(
          'source_language' => 'en',
          'target_language' => 'ru',
        ),
      'en__fr' =>
        array(
          'source_language' => 'en',
          'target_language' => 'fr',
        ),
      'fr__it' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'it',
        ),
      'fr__ru' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'ru',
        ),
      'it__en' =>
        array(
          'source_language' => 'it',
          'target_language' => 'en',
        ),
    ));
    $this->assertEqual(tmgmt_local_supported_language_pairs('fr', array($translator2->id())), array(
      'fr__it' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'it',
        ),
    ));

    // Test if we got all translators.
    $translators = tmgmt_local_translators();
    foreach ($all_translators as $uid => $name) {
      if (!isset($translators[$uid])) {
        $this->fail('Expected translator not present');
      }
      if (!in_array($name, $all_translators)) {
        $this->fail('Expected translator name not present');
      }
    }

    // Only translator2 has such abilities.
    $translators = tmgmt_local_translators('en', array('ru', 'fr'));
    $this->assertTrue(isset($translators[$translator2->id()]));
  }

  /**
   * Test permissions for the tmgmt_local VBO actions.
   */
  function testVBOPermissions() {
    $translator = Translator::load('local');
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->settings->job_comment = $job_comment = 'Dummy job comment';
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    // Create another local translator with the required abilities.
    $local_translator = $this->loginAsTranslator($this->local_translator_permissions);
    // Configure language abilities.
    $edit = array(
      'tmgmt_translation_skills[und][0][language_from]' => 'en',
      'tmgmt_translation_skills[und][0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $local_translator->id() . '/edit', $edit, t('Save'));

    $job->requestTranslation();

    $this->drupalGet('translate');
    $this->assertFieldById('edit-rules-componentrules-tmgmt-local-task-assign-to-me', t('Assign to me'));
    $this->assertFieldById('edit-rules-componentrules-tmgmt-local-task-unassign', t('Unassign'));

    // Login as admin and check VBO submit actions are present.
    $this->loginAsAdmin(array('administer translation tasks'));
    $this->drupalGet('manage-translate');
    $this->assertFieldById('edit-actionviews-bulk-operations-argument-selector-action--2', t('Assign to...'));
    $this->assertFieldById('edit-actionviews-bulk-operations-argument-selector-action', t('Assign to...'));
  }
}
