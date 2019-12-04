<?php

use Upal\DrupalUnitTestCase;

use Drupal\payment_context\NullPaymentContext;

/**
 * Integration test for webform_paymethod_select.
 */
class WebformPaymentTest extends DrupalUnitTestCase {

  /**
   * Prepare a test node.
   */
  public function setUp() {
    parent::setUp();
    $controller = payment_method_controller_load(wps_test_method_payment_method_controller_info()[0]);
    $method = entity_create('payment_method', ['controller' => $controller]);
    entity_save('payment_method', $method);
    $this->method = $method;

    module_load_include('components.inc', 'webform', 'includes/webform');
    $node = (object) ['type' => 'webform'];
    node_object_prepare($node);
    $node->webform['components'][1] = [
      'type' => 'paymethod_select',
      'pid' => 0,
      'form_key' => 'paymethod_select',
      'name' => 'Pay',
      'weight' => 0,
      'extra' => [
        'selected_payment_methods' => [$method->pmid => $method->pmid],
      ],
    ];
    foreach ($node->webform['components'] as &$c) {
      webform_component_defaults($c);
    }
    node_save($node);
    $this->node = node_load($node->nid);
    $GLOBALS['conf']['webform_tracking_mode'] = '<none>';
  }

  /**
   * Test a simple dummy payment.
   */
  public function testPayment() {
    $form_state['values']['submitted']['paymethod_select']['payment_method_all_forms'][$this->method->pmid] = [
      'validate_timeout' => 0,
    ];
    $form_state['values']['op'] = 'Submit';
    $form_state['values']['details']['sid'] = NULL;
    drupal_form_submit("webform_client_form_{$this->node->nid}", $form_state, $this->node);
    $submissions = webform_get_submissions(['nid' => $this->node->nid]);
    $this->assertCount(1, $submissions);
    $submission = reset($submissions);
    $payment = entity_load_single('payment', $submission->data[1][0]);
    $this->assertNotEmpty($payment);
  }

  /**
   * Test rendering the payment form with errors.
   */
  public function testRender() {
    $payment = new \Payment([
      'method' => $this->method,
      'contextObj' => new NullPaymentContext(),
    ]);
    $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
    entity_save('payment', $payment);
    $data[1][] = $payment->pid;
    $submission = (object) [
      'is_draft' => 1,
      'highest_valid_page' => 0,
      'data' => $data,
    ];
    $form = drupal_get_form('webform_client_form', $this->node, $submission);
    $this->assertEqual('webform_paymethod_select_error', $form['submitted']['paymethod_select']['error']['#theme']);
    $result = render($form['submitted']['paymethod_select']['error']);
    $this->assertContains('"Failed"', $result);
  }

  /**
   * Remove the test node.
   */
  public function tearDown() {
    entity_delete('payment_method', $this->method->pmid);
    node_delete($this->node->nid);
    parent::tearDown();
  }

}
