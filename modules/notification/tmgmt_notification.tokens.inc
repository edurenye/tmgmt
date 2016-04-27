<?php

/**
 * @file
 * Builds tokens for TMGMT notification.
 */

use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\SourcePreviewInterface;

/**
 * Implements hook_token_info().
 */
function tmgmt_notification_token_info() {
  // Token types.
  $tokens['types']['tmgmt_job_item'] = [
    'name' => t('Job Item'),
    'description' => t('Tokens for Job Item.'),
    'needs-data' => 'tmgmt_job_item',
  ];
  $tokens['types']['tmgmt_job'] = [
    'name' => t('Job'),
    'description' => t('Tokens for Job.'),
    'needs-data' => 'tmgmt_job',
  ];

  // Tokens.
  $tokens['tokens']['tmgmt_job_item']['label'] = [
    'name' => t("Label"),
    'description' => t("Label of the Job Item."),
  ];

  $tokens['tokens']['tmgmt_job_item']['url'] = [
    'name' => t("URL"),
    'description' => t("The URL of the Job Item."),
    'type' => 'url',
  ];

  $tokens['tokens']['tmgmt_job_item']['preview'] = [
    'name' => t("Preview URL"),
    'description' => t("The URL Preview of the Job Item source."),
    'type' => 'url',
  ];

  $tokens['tokens']['tmgmt_job_item']['translated_item_label'] = [
    'name' => t("Translated item label"),
    'description' => t("The label of translation of the Job Item."),
    'type' => 'url',
  ];

  $tokens['tokens']['tmgmt_job_item']['translated_item_url'] = [
    'name' => t("Translated item url"),
    'description' => t("The url of translation of the Job Item."),
    'type' => 'url',
  ];

  $tokens['tokens']['tmgmt_job']['label'] = [
    'name' => t("Label"),
    'description' => t("Label of the Job."),
  ];

  $tokens['tokens']['tmgmt_job']['url'] = [
    'name' => t("URL"),
    'description' => t("The URL of the Job."),
    'type' => 'url',
  ];

  return $tokens;
}

/**
 * Implements hook_tokens().
 */
function tmgmt_notification_tokens($type, $tokens, array $data = [], array $options = []) {
  $replacements = [];

  if ($type == 'tmgmt_job_item' && !empty($data['tmgmt_job_item'])) {
    if (($job_item = $data['tmgmt_job_item']) && $job_item instanceof JobItemInterface) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'label':
            $replacements[$original] = $job_item->label();
            break;

          case 'url':
            $replacements[$original] = $job_item->toUrl()->toString();
            break;

          case 'preview':
            if ($job_item->getSourcePlugin() instanceof SourcePreviewInterface) {
              /** @var \Drupal\tmgmt\SourcePreviewInterface $source */
              $source = $job_item->getSourcePlugin();
              $url = $source->getPreviewUrl($job_item);
              $replacements[$original] = $source->getPreviewUrl($job_item)->toString();
            }
            $replacements[$original] = isset($url) ? $url->toString() : '';
            break;

          case 'translated_item_label':
            try {
              $translation = \Drupal::entityTypeManager()->getStorage($job_item->getItemType())->load($job_item->getItemId());
              $translation = $translation->getTranslation($job_item->getJob()->getTargetLangcode());
            }
            catch (Exception $e) {
              $translation = NULL;
            }
            $replacements[$original] = $translation ? $translation->label() : $this->getSourceLabel();
            break;

          case 'translated_item_url':
            try {
              $translation = \Drupal::entityTypeManager()->getStorage($job_item->getItemType())->load($job_item->getItemId());
              $translation = $translation->getTranslation($job_item->getJob()->getTargetLangcode());
              $url = $translation->toUrl();
            }
            catch (Exception $e) {
              $url = NULL;
            }
            $replacements[$original] = isset($url) ? $url->toString() : '';
            break;

        }
      }
    }
  }

  if ($type == 'tmgmt_job' && !empty($data['tmgmt_job'])) {
    if (($tmgmt_job = $data['tmgmt_job']) && $tmgmt_job instanceof JobInterface) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'label':
            $replacements[$original] = $tmgmt_job->label();
            break;

          case 'url':
            $replacements[$original] = $tmgmt_job->toUrl()->toString();
            break;

        }
      }
    }
  }

  return $replacements;
}
