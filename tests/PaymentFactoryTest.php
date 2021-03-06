<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;
use Upal\DrupalUnitTestCase;

/**
 * Test the payment factory.
 */
class PaymentFactoryTest extends DrupalUnitTestCase {

  /**
   * Create a mock submission to get values from.
   */
  protected function submissionStub($cid_values, $key_values) {
    $submission = $this->createMock(Submission::class);
    $submission->method('valueByCid')->will($this->returnCallback(function ($cid) use ($cid_values) {
      if (isset($cid_values[$cid])) {
        return $cid_values[$cid];
      }
    }));
    $submission->method('valueByKey')->will($this->returnCallback(function ($key) use ($key_values) {
      if (isset($key_values[$key])) {
        return $key_values[$key];
      }
    }));
    return $submission;
  }

  /**
   * Scenario where everything is read from components.
   */
  public function testReadFromComponent() {
    $component['extra'] = [
      'currency_code_source' => 'component',
      'currency_code_component' => 1,
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
          'amount_source' => 'component',
          'amount_component' => 2,
          'quantity_source' => 'component',
          'quantity_component' => 3,
        ]),
      ],
    ];
    $cid_values = [
      1 => 'EUR',
      2 => 3.0,
      3 => 5,
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $factory->updatePayment($payment, $this->submissionStub($cid_values, []));
    $this->assertEqual('EUR', $payment->currency_code);
    $this->assertEqual(15.0, $payment->totalAmount(TRUE));
  }

  /**
   * Read recurrence from donation interval.
   */
  public function testReadFromDonationInterval() {
    $component['extra'] = [
      'currency_code_source' => 'fixed',
      'currency_code' => 'EUR',
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
          'amount_source' => 'fixed',
          'amount' => 3.0,
          'quantity_source' => 'fixed',
          'quantity' => 5,
        ]),
      ],
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $submission = $this->createMock(Submission::class);
    $key_values['donation_interval'] = 'm';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(15.0, $payment->totalAmount(TRUE));
    $this->assertEqual([
      'interval_unit' => 'monthly',
      'interval_value' => 1,
    ], (array) $payment->line_items['first_item']->recurrence);
  }

  /**
   * Test reading data from webform components via form keys.
   */
  public function testReadFromFormKeys() {
    $component['extra'] = [
      'currency_code_source' => 'fixed',
      'currency_code' => 'EUR',
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
        ]),
      ],
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $submission = $this->createMock(Submission::class);
    $key_values['payment__item1__amount'] = '3.0';
    $key_values['payment__item1__quantity'] = '5';
    $key_values['payment__item1__recurrence__interval_unit'] = 'monthly';
    $key_values['payment__item1__recurrence__interval_value'] = '1';
    $key_values['payment__item1__recurrence__day_of_month'] = '21';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(15.0, $payment->totalAmount(TRUE));
    $this->assertEqual([
      'interval_unit' => 'monthly',
      'interval_value' => '1',
      'day_of_month' => '21',
    ], (array) $payment->line_items['first_item']->recurrence);
  }

  /**
   * Test reading data from components and donation interval.
   */
  public function testReadMixedLegacyKeys() {
    $component['extra'] = [
      'currency_code_source' => 'fixed',
      'currency_code' => 'EUR',
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
        ]),
      ],
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $submission = $this->createMock(Submission::class);
    $key_values['payment__item1__amount'] = '3.0';
    $key_values['payment__item1__quantity'] = '5';
    $key_values['donation_interval'] = 'm';
    $key_values['payment__item1__recurrence__day_of_month'] = '21';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(15.0, $payment->totalAmount(TRUE));
    $this->assertEqual([
      'interval_unit' => 'monthly',
      'interval_value' => '1',
      'day_of_month' => '21',
    ], (array) $payment->line_items['first_item']->recurrence);
  }

  /**
   * Test reading data from keys ignores empty and invalid values.
   */
  public function testReadInvalidValues() {
    $component['extra'] = [
      'currency_code_source' => 'fixed',
      'currency_code' => 'EUR',
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
        ]),
      ],
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $submission = $this->createMock(Submission::class);
    $key_values['payment__item1__amount'] = '5not a number3';
    $key_values['payment__item1__quantity'] = '-3';
    $key_values['payment__item1__recurrence__interval_unit'] = 'monthly';
    $key_values['payment__item1__recurrence__interval_value'] = '-1';
    $key_values['payment__item1__recurrence__day_of_month'] = '';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(0.0, $payment->totalAmount(TRUE));
    $this->assertEqual([
      'interval_unit' => 'monthly',
    ], (array) $payment->line_items['first_item']->recurrence);
  }

  /**
   * Test recurrence start date.
   */
  public function testRecurrenceStartDate() {
    $date = new \DateTime('2019-09-09', new \DateTimeZone('UTC'));
    $component['extra'] = [
      'currency_code_source' => 'fixed',
      'currency_code' => 'EUR',
      'line_items' => [
        new PaymethodLineItem([
          'name' => 'first_item',
          'description' => 'First item',
          'amount_source' => 'fixed',
          'amount' => 3.0,
          'quantity_source' => 'fixed',
          'quantity' => 5,
        ]),
      ],
    ];
    $factory = new PaymentFactory($component);
    $payment = entity_create('payment', []);
    $submission = $this->createMock(Submission::class);
    $key_values['payment__item1__recurrence__interval_unit'] = 'monthly';
    // Test date object:
    $key_values['payment__item1__recurrence__start_date'] = $date;
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(
      $date, $payment->line_items['first_item']->recurrence->start_date
    );
    // Test timestamp → date:
    $key_values['payment__item1__recurrence__start_date'] = '1567987200';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(
      $date, $payment->line_items['first_item']->recurrence->start_date
    );
    // Test string → date:
    $key_values['payment__item1__recurrence__start_date'] = '2019-09-09';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertEqual(
      $date, $payment->line_items['first_item']->recurrence->start_date
    );
    // Test invalid date:
    $key_values['payment__item1__recurrence__start_date'] = '2019-19-19';
    $factory->updatePayment($payment, $this->submissionStub([], $key_values));
    $this->assertTrue(empty($payment->line_items['first_item']->recurrence->start_date));
  }

}
