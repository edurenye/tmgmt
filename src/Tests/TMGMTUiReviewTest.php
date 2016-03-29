<?php

/**
 * @file
 * Contains Drupal\tmgmt\Tests\TMGMTUiReviewTest.
 */

namespace Drupal\tmgmt\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Verifies the UI of the review form.
 *
 * @group tmgmt
 */
class TMGMTUiReviewTest extends EntityTestBase {

  public static $modules = ['tmgmt_content', 'image', 'node'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $this->addLanguage('de');

    $filtered_html_format = FilterFormat::create(array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ));
    $filtered_html_format->save();

    $this->drupalCreateContentType(array('type' => 'test_bundle'));

    $this->loginAsAdmin(array(
      'create translation jobs',
      'submit translation jobs',
      'create test_bundle content',
      $filtered_html_format->getPermissionName(),
    ));

    file_unmanaged_copy(DRUPAL_ROOT . '/core/misc/druplicon.png', 'public://example.jpg');
    $this->image = File::create(array(
      'uri' => 'public://example.jpg',
    ));
    $this->image->save();
  }


  /**
   * Tests of the job item review process.
   */
  public function testReview() {
    $job = $this->createJob();
    $job->translator = $this->default_translator->id();
    $job->settings = array();
    $job->save();
    $item = $job->addItem('test_source', 'test', 1);
    // The test expects the item to be active.
    $item->active();

    $data = \Drupal::service('tmgmt.data')->flatten($item->getData());
    $keys = array_keys($data);
    $key = $keys[0];

    $this->drupalGet('admin/tmgmt/items/' . $item->id());

    // Test that source and target languages are displayed.
    $this->assertText($item->getJob()->getSourceLanguage()->getName());
    $this->assertText($item->getJob()->getTargetLanguage()->getName());

    // Testing the title of the preview page.
    $this->assertText(t('Job item @source_label', array('@source_label' => $job->label())));

    // Testing the result of the
    // TMGMTTranslatorUIControllerInterface::reviewDataItemElement()
    $this->assertText(t('Testing output of review data item element @key from the testing provider.', array('@key' => $key)));

    // Test the review tool source textarea.
    $this->assertFieldByName('dummy|deep_nesting[source]', $data[$key]['#text']);

    // Save translation.
    $this->drupalPostForm(NULL, array('dummy|deep_nesting[translation]' => $data[$key]['#text'] . 'translated'), t('Save'));

    // Test review data item.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostAjaxForm(NULL, [], 'reviewed-dummy|deep_nesting');
    $this->assertRaw('icons/gray-check.svg" alt="Reviewed"');

    \Drupal::entityTypeManager()->getStorage('tmgmt_job')->resetCache();
    \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->resetCache();
    /** @var JobItem $item */
    $item = JobItem::load($item->id());
    $this->assertEqual($item->getCountReviewed(), 1, 'Item reviewed correctly.');

    // Check if translation has been saved.
    $this->assertFieldByName('dummy|deep_nesting[translation]', $data[$key]['#text'] . 'translated');

    // Tests for the minimum height of the textareas.
    $rows = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows[0]['rows'], 3);

    $rows2 = $this->xpath('//textarea[@name="dummy|deep_nesting[translation]"]');
    $this->assertEqual((string) $rows2[0]['rows'], 3);

    // Test data item status when content changes.
    $this->drupalPostForm(NULL, array(), t('Save'));
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertRaw('icons/gray-check.svg" alt="Reviewed"');
    $edit = [
      'dummy|deep_nesting[translation]' => 'New text for job item',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertRaw('icons/gray-check.svg" alt="Reviewed"');
    $this->assertFieldByName('dummy|deep_nesting[translation]', 'New text for job item');

    // Test for the dynamical height of the source textarea.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => str_repeat('Text for job item', 20),
          '#label' => 'Label',
        ),
      ),
    ));
    $item2 = $job->addItem('test_source', 'test', 2);
    $this->drupalGet('admin/tmgmt/items/' . $item2->id());

    $rows3 = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows3[0]['rows'], 4);

    // Test for the maximum height of the source textarea.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => str_repeat('Text for job item', 100),
          '#label' => 'Label',
        ),
      ),
    ));
    $item3 = $job->addItem('test_source', 'test', 3);
    $this->drupalGet('admin/tmgmt/items/' . $item3->id());

    $rows4 = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows4[0]['rows'], 15);

    // Tests the HTML tags validation.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Source text bold and Italic</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Source body bold and Italic</strong></em></p>',
          '#label' => 'Body',
        )
      ),
    ));
    $item4 = $job->addItem('test_source', 'test', 4);
    $this->drupalGet('admin/tmgmt/items/' . $item4->id());

    // Drop <strong> tag in translated text.
    $edit = array(
      'title|deep_nesting[translation]' => '<em>Translated italic text missing paragraph</em>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('Expected tags @tags not found.', array('@tags' => '<p>,<strong>,</strong>,</p>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '<p>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '<strong>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '</strong>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '</p>')));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Change the order of HTML tags.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><strong><em>Translated text Italic and bold</em></strong></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('Order of the HTML tags are incorrect.'));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Add multiple tags incorrectly.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><p><p><p><strong><em><em>Translated text Italic and bold, many tags</em></strong></strong></strong></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('@tag expected 1, found 4.', array('@tag' => '<p>')));
    $this->assertText(t('@tag expected 1, found 2.', array('@tag' => '<em>')));
    $this->assertText(t('@tag expected 1, found 3.', array('@tag' => '</strong>')));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Check validation errors for two fields.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><p><p><p><strong><em><em>Translated text Italic and bold, many tags</em></strong></strong></strong></p>',
      'body|deep_nesting[translation]' => '<p>Source body bold and Italic</strong></em></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('HTML tag validation failed for 2 field(s).'));

    // Tests that there is always a title.
    $text = '<p><em><strong>Source text bold and Italic</strong></em></p>';
    \Drupal::state()->set('tmgmt.test_source_data', [
      'title' => [
        [
          'value' => [
            '#text' => $text,
            '#label' => 'Title',
            '#translate' => TRUE,
            '#format' => 'filtered_html',
          ],
        ],
      ],
      'body' => [
        'deep_nesting' => [
          '#text' => $text,
          '#label' => 'Body',
          '#translate' => TRUE,
          '#format' => 'filtered_html',
        ],
      ],
    ]);
    $item5 = $job->addItem('test_source', 'test', 4);

    $this->drupalPostForm('admin/tmgmt/items/' . $item5->id(), [], t('Validate'));
    $this->assertText(t('The field is empty.'));

    // Test review just one data item.
    $edit = [
      'title|0|value[translation][value]' => $text . 'translated',
      'body|deep_nesting[translation][value]' => $text . 'no save',
    ];
    $this->drupalPostAjaxForm('admin/tmgmt/items/' . $item5->id(), $edit, 'reviewed-title|0|value');

    // Check if translation has been saved.
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertFieldByName('title|0|value[translation][value]', $text . 'translated');
    $this->assertNoFieldByName('body|deep_nesting[translation][value]', $text . 'no save');

    // Tests field is less than max_length.
    \Drupal::state()->set('tmgmt.test_source_data', [
      'title' => [
        [
          'value' => [
            '#text' => $text,
            '#label' => 'Title',
            '#translate' => TRUE,
            '#max_length' => 10,
          ],
        ],
      ],
      'body' => [
        'deep_nesting' => [
          '#text' => $text,
          '#label' => 'Body',
          '#translate' => TRUE,
          '#max_length' => 20,
        ],
      ],
    ]);
    $item5 = $job->addItem('test_source', 'test', 4);

    $this->drupalPostForm('admin/tmgmt/items/' . $item5->id(), [
      'title|0|value[translation]' => $text,
      'body|deep_nesting[translation]' => $text,
    ], t('Save'));
    $this->assertText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 10,
    ]));
    $this->assertText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 20,
    ]));

    // Test if the validation is properly done.
    $this->drupalPostAjaxForm(NULL, [], 'reviewed-body|deep_nesting');
    $this->assertUniqueText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 10,
    ]));

    // Test for the text with format set.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => 'Text for job item',
          '#label' => 'Label',
          '#format' => 'filtered_html',
        ),
      ),
    ));
    $item5 = $job->addItem('test_source', 'test', 5);
    $item5->active();

    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->assertText('The translation of test_source:test:1 to German is finished and can now be reviewed.');
    $this->clickLink(t('reviewed'));
    $this->assertText('Needs review');
    $this->assertText('Job item test_source:test:1');

    $edit = array(
      'target_language' => 'de',
      'settings[action]' => 'submit',
    );
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), $edit, t('Submit to provider'));

    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $xpath = $this->xpath('//*[@id="edit-dummydeep-nesting-translation-format-guidelines"]/div')[0];
    $this->assertEqual($xpath[0]->h4[0], t('Filtered HTML'));
    $rows5 = $this->xpath('//textarea[@name="dummy|deep_nesting[source][value]"]');
    $this->assertEqual((string) $rows5[0]['rows'], 3);

    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertNoText('has been saved successfully.');
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertText('In progress');
    $edit = array(
      'dummy|deep_nesting[translation][value]' => 'Translated text for job item',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText('The translation for ' . trim($item5->label()) . ' has been saved successfully.');
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertText('Translated text for job item');
    $this->drupalPostForm(NULL, $edit, t('Save as completed'));
    $this->assertEqual(\Drupal::state()->get('tmgmt_test_saved_translation_' . $item5->getItemType() . '_' . $item5->getItemId())['dummy']['deep_nesting']['#translation']['#text'], 'Translated text for job item');

    // Test if the icons are displayed.
    $this->assertRaw('views-field-progress">Accepted');
    $this->assertRaw('icons/ready.svg" title="Needs review"');
    $this->loginAsAdmin();

    // Create two translators.
    $translator1 = $this->createTranslator();
    $translator2 = $this->createTranslator();
    $this->drupalGet('admin/tmgmt/jobs');

    // Assert that translators are in dropdown list.
    $this->assertOption('edit-translator', $translator1->id());
    $this->assertOption('edit-translator', $translator2->id());

    // Assign each job to a translator.
    $job1 = $this->createJob();
    $this->drupalGet('admin/tmgmt/jobs');
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[1]);

    $job2 = $this->createJob();
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertTrue($label, trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[1]));
    $job1->set('translator', $translator1->id())->save();
    $job2->set('translator', $translator2->id())->save();

    // Test that progress bar is being displayed.
    $this->assertRaw('class="tmgmt-progress-pending" style="width: 50%"');

    // Filter jobs by translator and assert values.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('translator' => $translator1->id())));
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[4]);
    $this->assertEqual($label, $translator1->label(), 'Found provider label in table');
    $this->assertNotEqual($label, $translator2->label(), "Providers filtered in table");
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('translator' => $translator2->id())));
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[4]);
    $this->assertEqual($label, $translator2->label(), 'Found provider label in table');
    $this->assertNotEqual($label, $translator1->label(), "Providers filtered in table");

    $edit = array(
      'dummy|deep_nesting[translation]' => '',
    );
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostForm(NULL, $edit, t('Validate'));
    $this->assertText(t('The field is empty.'));

    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertNoText(t('The field is empty.'));

    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostForm(NULL, [], t('Save as completed'));
    $this->assertText(t('The field is empty.'));

    // Test validation message for 'Validate' button.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $translation_field = $this->randomMachineName();
    $edit = array(
      'dummy|deep_nesting[translation]' => $translation_field,
    );
    $this->drupalPostForm(NULL, $edit, t('Validate'));
    $this->assertText(t('Validation completed successfully.'), 'Message is correctly displayed.');

    // Test validation message for 'Validate HTML tags' button.
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('Validation completed successfully.'), 'Message is correctly displayed.');

    // Test that normal job item are shown in job items overview.
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => 'All')));
    $this->assertNoText($job1->label(), 'Normal job item is displayed on job items overview.');

    // Test that the legend is being displayed.
    $this->assertRaw('class="tmgmt-color-legend clearfix"');

    // Test that progress bar is being displayed.
    $this->assertRaw('class="tmgmt-progress-reviewed" style="width: 100%"');
  }

  /**
   * Tests of the job item review process.
   */
  public function testReviewForm() {
    // Create the field body with multiple delta.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'body_test',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => -1,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'field_storage' => $field_storage,
      'bundle' => 'test_bundle',
    ))->save();

    // Create the field image with multiple value and delta.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'image_test_multi',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => -1,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'field_storage' => $field_storage,
      'bundle' => 'test_bundle',
    ))->save();

    // Create the field image with multiple value and delta.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'image_test_single',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => 1,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'field_storage' => $field_storage,
      'bundle' => 'test_bundle',
    ))->save();

    // Create two images.
    $image1 = array(
      'target_id' => $this->image->id(),
      'alt' => $this->randomMachineName(),
      'title' => $this->randomMachineName(),
    );
    $image2 = array(
      'target_id' => $this->image->id(),
      'alt' => $this->randomMachineName(),
      'title' => $this->randomMachineName(),
    );

    // Create the node.
    $settings = array(
      'title' => $this->randomMachineName(),
      'type' => 'test_bundle',
      'body_test' => array(
        $this->randomMachineName(),
        $this->randomMachineName(),
      ),
      'image_test_single' => $image1,
      'image_test_multi' => array($image1, $image2),
    );
    $node = Node::create($settings);
    $node->save();

    // Create a Job with the node.
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), array('tjid' => $job->id()));
    $job_item->save();

    // Access to the review form.
    $this->drupalGet('admin/tmgmt/items/1');

    // Test that all the items are being displayed.
    $this->assertRaw('name="title|0|value[source]"');
    $this->assertRaw('name="body_test|0|value[source]"');
    $this->assertRaw('name="body_test|1|value[source]"');
    $this->assertRaw('name="image_test_multi|0|title[source]"');
    $this->assertRaw('name="image_test_multi|0|alt[source]"');
    $this->assertRaw('name="image_test_multi|1|title[source]"');
    $this->assertRaw('name="image_test_multi|1|alt[source]"');
    $this->assertRaw('name="image_test_single|0|title[source]"');
    $this->assertRaw('name="image_test_single|0|alt[source]"');

    // Check the labels for the title.
    $this->assertEqual($this->xpath('//*[@id="tmgmt-ui-element-title-wrapper"]/table/tbody/tr[1]/th'), NULL);
    $this->assertEqual($this->xpath('//*[@id="tmgmt-ui-element-title-wrapper"]/table/tbody/tr[2]/td[1]/div[1]/label'), NULL);

    // Check the labels for the multi delta body.
    $delta = $this->xpath('//*[@id="tmgmt-ui-element-body-test-wrapper"]/table/tbody/tr[1]/td[1]/div[1]/label');
    $this->assertEqual($delta[0], 'Delta #0');
    $delta = $this->xpath('//*[@id="tmgmt-ui-element-body-test-wrapper"]/table/tbody[2]/tr[1]/td[1]/div[1]/label');
    $this->assertEqual($delta[0], 'Delta #1');

    // Check the labels for the multi delta/multi value image.
    $delta = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[1]/tr[1]/th');
    $this->assertEqual($delta[0], 'Delta #0');
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[1]/tr[2]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Alternative text');
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[1]/tr[4]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Title');
    $delta = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[2]/tr[1]/th');
    $this->assertEqual($delta[0], 'Delta #1');
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[2]/tr[2]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Alternative text');
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-multi-wrapper"]/table/tbody[2]/tr[4]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Title');

    // Check the labels for the multi value image.
    $this->assertEqual($this->xpath('//*[@id="tmgmt-ui-element-image-test-single-wrapper"]/table/tbody/tr[1]/th'), NULL);
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-single-wrapper"]/table/tbody/tr[1]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Alternative text');
    $label = $this->xpath('//*[@id="tmgmt-ui-element-image-test-single-wrapper"]/table/tbody/tr[3]/td[1]/div[1]/label');
    $this->assertEqual($label[0], 'Title');
  }

  /**
   * Tests text format permissions on translation fields.
   */
  public function testTextFormatPermissions() {
    // Create a job.
    $job1 = $this->createJob();
    $job1->save();
    $job1->setState(Job::STATE_ACTIVE);

    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
          '#format' => 'full_html',
        )
      ),
    ));
    $item1 = $job1->addItem('test_source', 'test', 1);
    $item1->setState(JobItem::STATE_REVIEW);
    $item1->save();
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());

    // Assert that translator has no permission to review/update "body" field.
    $source_field_message = $this->xpath('//*[@id="edit-bodydeep-nesting-source"]')[0];
    $translation_field_message = $this->xpath('//*[@id="edit-bodydeep-nesting-translation"]')[0];
    $this->assertEqual($source_field_message, t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'));
    $this->assertEqual($translation_field_message, t('This field has been disabled because you do not have sufficient permissions to edit it. It is not possible to review or accept this job item.'));
    $this->assertNoRaw('Save as completed" class="button button--primary js-form-submit form-submit"');

    // Remove full html format from the body field.
    $item1->updateData('body|deep_nesting', ['#format' => '']);
    $item1->save();

    // Translator should see enabled translation field again.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->assertRaw('Save as completed" class="button button--primary js-form-submit form-submit"');
    $this->assertFieldByName('body|deep_nesting[translation]');
    $translation_field = $this->xpath('//*[@id="edit-bodydeep-nesting-translation"]')[0];
    $this->assertEqual($translation_field, '');
  }

  /**
   * Tests update the source and show the diff of the source.
   */
  public function testSourceUpdate() {
    // Create the original data items.
    $job = $this->createJob('en', 'de');
    $job->translator = $this->default_translator;
    $job->save();
    \Drupal::state()->set('tmgmt.test_source_data', [
      'title' => [
        '#label' => 'Example text 1',
        'deep_nesting' => [
          '#text' => 'Text for job item with type test and id 1.',
          '#label' => 'Example text 1',
          '#translate' => TRUE,
        ],
      ],
      'sayonara_text' => [
        '#label' => 'Example text 2',
        'deep_nesting' => [
          '#text' => 'This text will end badly.',
          '#label' => 'Example text 2',
          '#translate' => TRUE,
        ],
      ],
    ]);
    $job->addItem('test_source', 'test', '1');
    $job->save();

    $edit = array(
      'target_language' => 'de',
      'settings[action]' => 'submit',
    );
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), $edit, t('Submit to provider'));

    $job->requestTranslation();

    // Modify the source.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        '#label' => 'Example text modified',
        'deep_nesting' => array(
          '#text' => 'This source has been changed.',
          '#label' => 'Example text modified',
          '#translate' => TRUE,
        ),
      ),
    ));

    // Show a message informing of the conflicts in the sources.
    $this->drupalGet('admin/tmgmt/items/1');
    $this->assertText('The source has changed.');
    $this->assertText('This data item has been removed from the source.');

    // Show changes as diff.
    $this->drupalPostAjaxForm(NULL, [], 'diff-button-title|deep_nesting');
    $this->assertNoText('The source has changed.');
    $this->assertText('Text for job item with type test and id 1.');
    $this->assertText('This source has been changed.');
    $this->assertText('This data item has been removed from the source.');

    // Resolve the first data item.
    $this->drupalPostAjaxForm(NULL, [], 'resolve-diff-title|deep_nesting');
    $this->assertText('The conflict in the data item source "Example text modified" has been resolved.');
    $this->assertNoText('The source has changed.');
    $xpath = $this->xpath('//*[@name="title|deep_nesting[source]"]')[0];
    $this->assertEqual($xpath, 'This source has been changed.');

    // Check the other data item was not modified.
    $this->assertText('This data item has been removed from the source.');
    $this->assertText('This text will end badly.');
  }

}
