<?php

namespace Drupal\webform_paymethod_select;

use Upal\DrupalUnitTestCase;

/**
 * Test processing a payment line item.
 */
class PaymentLineItemTest extends DrupalUnitTestCase {

  /**
   * Test processing and validating a line item.
   */
  public function testProcessAndValidateDefaults() {
    $node_stub = (object) [
      'webform' => [
        'components' => [],
      ],
    ];
    $form_id = 'payment_line_item_test';
    $form['#node'] = $node_stub;
    $form['line_item'] = [
      '#type' => 'payment_line_item',
    ];
    $form_state = form_state_defaults();
    $form_state['build_info']['form_id'] = $form_id;
    drupal_prepare_form($form_id, $form, $form_state);
    drupal_process_form($form_id, $form, $form_state);

    $this->assertNotEmpty($form['line_item']['container_0']['quantity']['source']);

    // Fake submitting the form.
    $item = &$form['line_item']['container_0'];
    $item['description']['#value'] = 'test-line-item';
    $item['amount']['source']['#value'] = 'fixed';
    $item['amount']['value']['#value'] = '1';
    webform_paymethod_select_element_validate_line_item($form['line_item'], $form_state);

    $messages = drupal_get_messages();
    // No error messages expected.
    $this->assertEqual([], $messages);
  }

}
