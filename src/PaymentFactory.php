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
    $cast_float = function ($v) {
      return is_numeric($v) ? (float) $v : NULL;
    };
    $cast_string = function ($v) {
      return $v ? $v : NULL;
    };
    $cast_int_factory = function ($min = NULL, $max = NULL) {
      return function ($v) use ($min, $max) {
        $i = (int) $v;
        return $i == $v && (is_null($min) || $i >= $min) && (is_null($max) || $i <= $max) ? $i : NULL;
      };
    };
    $map = [
      'amount' => ['amount', $cast_float],
      'quantity' => ['quantity', $cast_int_factory(0)],
      'description' => ['description', $cast_string],
      'tax_rate' => ['tax_rate', $cast_float],
      'recurrence.interval_unit' => ['recurrence__interval_unit', $cast_string],
      'recurrence.interval_value' => ['recurrence__interval_value', $cast_int_factory(1)],
      'recurrence.day_of_month' => ['recurrence__day_of_month', $cast_int_factory(-31, 31)],
      'recurrence.month' => ['recurrence__month', $cast_int_factory(1, 12)],
      'recurrence.start_data' => ['recurrence__start_date', $cast_string],
      'recurrence.count' => ['recurrence__count', $cast_int_factory(0)],
    ];

    $prefix = "payment__item{$index}__";
    foreach ($map as $target => $m) {
      list($form_key, $cast_fn) = $m;
      if (!($value = $submission->valueByKey($prefix . $form_key))) {
        // Never set empty values. They could be the result of a conditionally
        // hidden component.
        continue;
      }
      $value = $cast_fn($value);
      if (isset($value)) {
        static::objDeepSet($line_item, explode('.', $target), $value);
      }
    }

    if (empty($line_item->recurrence->interval_unit)) {
      $line_item->recurrence = NULL;
    }
  }

  /**
   * Set a value based on keys in a nested obj data structure.
   *
   * @param object $obj
   *   Object which’s property should be set.
   * @param string[] $keys
   *   Array of property names.
   * @param mixed $value
   *   The property is set to this value.
   */
  protected static function objDeepSet($obj, array $keys, $value) {
    $key = array_shift($keys);
    if ($keys) {
      if (!isset($obj->$key)) {
        $obj->$key = (object) [];
      }
      static::objDeepSet($obj->$key, $keys, $value);
    }
    else {
      $obj->$key = $value;
    }
  }

}
