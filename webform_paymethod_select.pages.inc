<?php

/**
 * Page callback for continuing a webform submission after payment.
 */
function webform_paymethod_select_continue($node, $submission, $page_num) {
  // We can't use a simple drupal_get_form() because we wan't to enter the form
  // at a specific $page_num.
  $form_state = array();

  $submission->highest_valid_page = $page_num - 1;
  $form_state['build_info']['args'] = [$node, $submission, FALSE, TRUE];
  // Needed to accomodate little_helpers.
  $form_state['values']['details']['sid'] = $submission->sid;
  return drupal_build_form('webform_client_form_' . $node->nid, $form_state);
}
