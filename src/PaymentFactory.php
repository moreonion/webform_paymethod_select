<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;


/**
 * Updates payment objects based on the component configuration and submission.
 */
class PaymentFactory {

  const DONATION_INTERVAL_MAP = [
    'm' => ['interval_unit' => 'monthly', 'interval_value' => 1],
    'q' => ['interval_unit' => 'monthly', 'interval_value' => 3],
    'y' => ['interval_unit' => 'yearly', 'interval_value' => 1],
  ];

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

    // Set recurrence based on the donation_interval component (legacy).
    $recurrence = NULL;
    if ($value = $submission->valueByKey('donation_interval')) {
      $map = static::DONATION_INTERVAL_MAP;
      if (isset($map[$value])) {
        $recurrence = (object) $map[$value];
      }
    }

    // Set the payment up for a (possibly repeated) payment attempt.
    // Handle setting the amount value in line items that were configured to
    // read their amount from a component.
    $index = 1;
    foreach ($extra['line_items'] as $line_item) {
      $this->lineItemFromKeys($line_item, $index, $submission);
      if (isset($line_item->amount_source) && $line_item->amount_source === 'component') {
        $amount = $submission->valueByCid($line_item->amount_component);
        $amount = str_replace(',', '.', $amount);
        $line_item->amount = (float) $amount;
      }
      if (isset($line_item->quantity_source) && $line_item->quantity_source === 'component') {
        $quantity = $submission->valueByCid($line_item->quantity_component);
        $line_item->quantity = (int) $quantity;
      }
      if (empty($line_item->recurrence)) {
        $line_item->recurrence = $recurrence;
      }
      $payment->setLineItem($line_item);
      $index++;
    }
  }

  /**
   * Read line item data from a pre-defined
   */
  protected function lineItemFromKeys(\PaymentLineItem $line_item, $index, Submission $submission) {
    $prefix = "payment__item{$index}__";
    $map = [
      'amount' => 'amount',
      'quantity' => 'quantity',
      'description' => 'description',
      'tax_rate' => 'tax_rate',
      'recurrence' => [
        'interval_unit' => 'recurrence__interval_unit',
        'interval_value' => 'recurrence__interval_value',
        'day_of_month' => 'recurrence__day_of_month',
        'month' => 'recurrence__month',
        'start_data' => 'recurrence__start_date',
        'count' => 'recurrence__count',
      ],
    ];
    $q = [[$map, $line_item]];

    // Recursively fill the object.
    while ($item = array_shift($q)) {
      list($m, $obj) = $item;
      foreach ($m as $attr => $key) {
        if (is_array($key)) {
          if (!isset($obj->$attr)) {
            $obj->$attr = (object) [];
          }
          $q[] = [$key, $obj->$attr];
        }
        else {
          $value = $submission->valueByKey($prefix . $key);
          if (isset($value)) {
            $obj->$attr = $value;
          }
        }
      }
    }

    if (empty($line_item->recurrence->interval_unit)) {
      $line_item->recurrence = NULL;
    }
  }

}
