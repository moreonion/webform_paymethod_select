<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;

/**
 *
 */
class PaymethodLineItem extends \PaymentLineItem {
  public $amount_source    = 'fixed';
  public $amount_component = NULL;
  public $quantity_source    = 'fixed';
  public $quantity_component = NULL;
  public $recurrence = NULL;

  public function export() {
    $serialized = serialize($this);
    return "unserialize('$serialized')";
  }
}
