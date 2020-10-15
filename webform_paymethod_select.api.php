<?php

/**
 * @file
 * Document hooks invoked by this module.
 */

use Drupal\little_helpers\Webform\Submission;

/**
 * Alter the available payment methods for a payment.
 *
 * @param \PaymentMethod[] $methods
 *   Payment options that will be offered for this payment.
 * @param \Payment $payment
 *   The payment for which the methods are selected.
 */
function hook_webform_paymethod_select_method_list(array &$methods, \Payment $payment) {
  // Example: Disallow cash for big amounts.
  if ($payment->totalAmount() > 1000) {
    foreach ($methods as $pmid => $method) {
      if ($method->controller->name == 'cash') {
        unset($methods[$pmid]);
      }
    }
  }
}

/**
 * Alter how the payment is generated based on the submission.
 *
 * @param \Payment $payment
 *   The payment to be altered.
 * @param array $component
 *   The webform component for which the payment is generated.
 * @param \Drupal\little_helpers\Webform\Submission $submission
 *   The submission for which the payment is generated.
 */
function hook_webform_paymethod_select_payment_alter(\Payment &$payment, array $component, Submission $submission) {
  // Example: Remove line items that have a zero quantity or a zero amount.
  foreach ($payment->line_items as $name => $item) {
    if ($item->quantity == 0 || $item->totalAmount() == 0) {
      unset($payment->line_items[$name]);
    }
  }
}
