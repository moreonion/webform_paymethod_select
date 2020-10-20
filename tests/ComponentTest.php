<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;
use Upal\DrupalUnitTestCase;

/**
 * Test the webform component object.
 */
class ComponentTest extends DrupalUnitTestCase {

  /**
   * Create a test node.
   */
  public function setUp() {
    parent::setUp();
    module_load_include('inc', 'webform', 'includes/webform.components');
    module_load_include('inc', 'webform', 'includes/webform.submissions');
    $node = (object) [
      'type' => 'webform',
      'title' => static::class,
    ];
    node_object_prepare($node);
    $node->webform['components'][1] = [
      'type' => 'paymethod_select',
    ];
    $node->webform['components'][2] = [
      'type' => 'email',
    ];
    foreach ($node->webform['components'] as $cid => &$component) {
      $component['cid'] = $cid;
      webform_component_defaults($component);
    }
    node_save($node);
    $this->node = node_load($node->nid);
    $this->payment = NULL;
  }

  /**
   * Delete the test node.
   */
  public function tearDown() {
    node_delete($this->node->nid);
    if ($this->payment) {
      entity_delete('payment', $this->payment->pid);
    }
    parent::tearDown();
  }

  /**
   * Test invoking the AJAX callback.
   */
  public function testAjaxCallback() {
    $component = new Component($this->node->webform['components'][1]);
    $controller = $this->getMockBuilder(\PaymentMethodController::class)
      ->setMethods(['ajaxCallback'])
      ->getMock();
    $self = $this;
    $controller->method('ajaxCallback')->will($this->returnCallback(function (\Payment $payment) use ($self) {
      // Component::render() unsets this, but payment_context_entity_presave()
      // expects it to be at least NULL.
      $payment->contextObj = NULL;
      entity_save('payment', $payment);
      $self->payment = $payment;
    }));
    $method = entity_create('payment_method', [
      'controller' => $controller,
    ]);
    $element = webform_component_invoke('paymethod_select', 'render', $this->node->webform['components'][1]);
    $form = ['#node' => $this->node];
    $form_state = [];
    $component->render($element, $form, $form_state);
    $component->executeAjaxCallback($method, $form, $form_state);

    // The $submission->sid is in the right place to be found in the following
    // form submit.
    $this->assertNotEmpty($form['details']['sid']['#value']);

    // Load and inspect the submission and payment.
    $submission = Submission::load($this->node->nid, $form['details']['sid']['#value']);
    $payment = entity_load_single('payment', $submission->data[1][0]);
    $this->assertEqual([
      'nid' => $this->node->nid,
      'sid' => $form['details']['sid']['#value'],
      'cid' => 1,
    ], $payment->contextObj->toContextData());
  }

}
