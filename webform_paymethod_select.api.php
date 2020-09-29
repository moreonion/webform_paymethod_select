<?php

/**
 * @file
 * Document hooks invoked by this module.
 */

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
