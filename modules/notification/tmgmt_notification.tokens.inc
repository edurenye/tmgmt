<?php

/**
 * @file
 * Builds tokens for TMGMT notification.
 */

use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

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
    if (($tmgmt_job_item = $data['tmgmt_job_item']) && $tmgmt_job_item instanceof JobItemInterface) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'label':
            $replacements[$original] = $tmgmt_job_item->label();
            break;

          case 'url':
            $replacements[$original] = $tmgmt_job_item->toUrl()->toString();
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
