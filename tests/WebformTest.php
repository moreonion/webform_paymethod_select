<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\ArrayConfig;
use Drupal\payment_context\NullPaymentContext;
use Drupal\wps_test_method\DummyController;
use Upal\DrupalUnitTestCase;

/**
 * Test the webform hook implementations.
 */
class WebformTest extends DrupalUnitTestCase {

  /**
   * Create a payment method stub for testing.
   */
  protected function paymentMethodStub() {
    $controller = new DummyController();
    $method = new \PaymentMethod([
      'controller' => $controller,
      'controller_data' => [],
      'title_specific' => 'Test method',
    ]);
    ArrayConfig::mergeDefaults($method->controller_data, $controller->controller_data_defaults);
    return $method;
  }

  /**
   * Create a payment stub for testing.
   */
  protected function paymentStub() {
    $method = $this->paymentMethodStub();
    $context = $this->createMock(NullPaymentContext::class);
    $payment = new \Payment([
      'pid' => 42,
      'description' => 'test payment',
      'currency_code' => 'EUR',
      'method' => $method,
      'contextObj' => $context,
    ]);
    return $payment;
  }

  /**
   * Test getting the submission data with the patch from #3086038.
   *
   * @link https://www.drupal.org/node/3086038 #3086038 @endlink
   */
  public function testSubmissionDataWith3086038() {
    $submission = (object) ['payments' => []];
    $submission->payments[$cid = 3] = $this->paymentStub();
    $data = webform_paymethod_select_webform_results_download_submission_information_data_row($submission, [], 0, 1);
    $this->assertEqual([
      'payment_pid' => 42,
      'payment_method' => 'Test method',
      'payment_status' => 'payment_status_new',
    ], $data);
  }

  /**
   * Test getting the submission data without the patch from #3086038.
   */
  public function testSubmissionDataWithoutPatch() {
    $submission = (object) ['payments' => []];
    $submission->payments[$cid = 3] = $this->paymentStub();
    $data = webform_paymethod_select_webform_results_download_submission_information_data('payment_method', $submission, [], 0, 1);
    $this->assertEqual('Test method', $data);
  }

}
