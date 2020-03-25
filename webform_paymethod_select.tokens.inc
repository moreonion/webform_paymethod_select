<?php

/**
 * @file
 * Implementation hooks for the Drupal token system.
 */

use Drupal\little_helpers\Webform\Submission;

use Drupal\webform_paymethod_select\Tokens;

/**
 * Implements hook_tokens().
 */
function webform_paymethod_select_tokens(string $type, array $tokens, array $data = [], array $options = []) {
  // Return early unless submission tokens are needed and there is a submission.
  if ($type != 'submission' || empty($data['webform-submission']) || !webform_variable_get('webform_token_access')) {
    return [];
  }

  $replacements = [];
  $node = isset($data['node']) ? $data['node'] : node_load($data['webform-submission']->nid);
  $submission = new Submission($node, $data['webform-submission']);

  if ($value_tokens = Tokens::unprefixTokens($tokens, ['values'])) {
    $component_parents = [];
    foreach ($submission->webform->componentsByType('paymethod_select') as $cid => $component) {
      $parents = webform_component_parent_keys($node, $component);
      $parents[] = 'payment';
      $payment_tokens = Tokens::unprefixTokens($value_tokens, $parents);
      if ($payment_tokens && ($pid = $submission->valueByCid($cid)) && ($payment = entity_load_single('payment', $pid))) {
        // Allow payment tokens to be added.
        $replacements += token_generate('payment', $payment_tokens, $data + [
          'payment' => $payment,
        ], $options);
      }
    }
  }
  return $replacements;
}
