<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;


/**
 * Updates payment objects based on the component configuration and submission.
 */
class PaymentFactory {

  protected $component;

  /**
   * Create the factory by passing the component configuration.
   *
   * @param array $component
   *   A paymethod_select webform component.
   */
  public function __construct(array $component) {
    $this->component = $component;
  }

  /**
   * Update a payment according to submission and component data.
   *
   * @param \Payment $payment
   *   The payment to update
   * @param \Drupal\little_helpers\Webform\Submission $submission
   *   The submission data.
   */
  public function updatePayment(\Payment $payment, Submission $submission) {
    $extra = $this->component['extra'];

    if ($extra['currency_code_source'] === 'component') {
      $payment->currency_code = $submission->valueByCid($extra['currency_code_component']);
    }

    // Set the payment up for a (possibly repeated) payment attempt.
    // Handle setting the amount value in line items that were configured to
    // read their amount from a component.
    foreach ($extra['line_items'] as $line_item) {
      if (isset($line_item->amount_source) && $line_item->amount_source === 'component') {
        $amount = $submission->valueByCid($line_item->amount_component);
        $amount = str_replace(',', '.', $amount);
        $line_item->amount = (float) $amount;
      }
      if (isset($line_item->quantity_source) && $line_item->quantity_source === 'component') {
        $quantity = $submission->valueByCid($line_item->quantity_component);
        $line_item->quantity = (int) $quantity;
      }
      $payment->setLineItem($line_item);
    }
  }

}
