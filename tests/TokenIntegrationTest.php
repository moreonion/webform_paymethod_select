<?php

namespace Drupal\webform_paymethod_select;

use Upal\DrupalUnitTestCase;

/**
 * Integration test for resolving payment tokens in webform submissions.
 */
class TokenIntegrationTest extends DrupalUnitTestCase {

  /**
   * Prepare a test node.
   */
  public function setUp() : void {
    parent::setUp();
    module_load_include('components.inc', 'webform', 'includes/webform');
    module_load_include('submissions.inc', 'webform', 'includes/webform');
    $controller = payment_method_controller_load(wps_test_method_payment_method_controller_info()[0]);
    $method = entity_create('payment_method', ['controller' => $controller]);
    entity_save('payment_method', $method);
    $this->method = $method;
    $payment = entity_create('payment', ['method' => $method]);
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'test',
      'amount' => 5,
      'quantity' => 2,
    ]));
    entity_save('payment', $payment);
    $this->payment = $payment;

    $node = (object) ['type' => 'webform'];
    node_object_prepare($node);
    $node->webform['components'][1] = [
      'type' => 'paymethod_select',
      'form_key' => 'paymethod_select',
      'name' => 'Pay',
      'extra' => [
        'selected_payment_methods' => [$method->pmid => $method->pmid],
      ],
    ];
    foreach ($node->webform['components'] as $cid => &$component) {
      webform_component_defaults($component);
      $component += [
        'cid' => $cid,
        'pid' => 0,
        'weight' => 0,
      ];
    }
    node_save($node);
    $this->node = node_load($node->nid);
  }

  /**
   * Test payment token replacement inside submissions.
   */
  public function testTokenReplacement() {
    // Test that token replacement works with the payment directly.
    $actual = token_replace('[payment:line_item-payment_all]', ['payment' => $this->payment]);
    $this->assertEqual('10', $actual);
    // Test that it also works via a submission.
    $s = [
      'sid' => 'test-sid',
      'completed' => 1,
    ];
    $s['data'][1][] = $this->payment->pid;
    $data['node'] = $this->node;
    $data['webform-submission'] = (object) $s;
    $actual = token_replace('[submission:values:paymethod_select:payment:line_item-payment_all]', $data);
    $this->assertEqual('10', $actual);
  }

  /**
   * Remove the test node.
   */
  public function tearDown() : void {
    entity_delete('payment', $this->payment->pid);
    entity_delete('payment_method', $this->method->pmid);
    node_delete($this->node->nid);
    parent::tearDown();
  }

}
