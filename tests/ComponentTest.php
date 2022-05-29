<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;
use Upal\DrupalUnitTestCase;

/**
 * Test the webform component object.
 */
class ComponentTest extends DrupalUnitTestCase {

  /**
   * Create a new wps test payment method.
   */
  protected function createTestMethod() {
    $controller = payment_method_controller_load(wps_test_method_payment_method_controller_info()[0]);
    $method = entity_create('payment_method', ['controller' => $controller]);
    entity_save('payment_method', $method);
    return $method;
  }

  /**
   * Create a test node.
   */
  public function setUp() : void {
    parent::setUp();
    $this->method = $this->createTestMethod();
    module_load_include('inc', 'webform', 'includes/webform.components');
    module_load_include('inc', 'webform', 'includes/webform.submissions');
    $node = (object) [
      'type' => 'webform',
      'title' => static::class,
    ];
    node_object_prepare($node);
    $node->webform['components'][1] = [
      'type' => 'paymethod_select',
      'form_key' => 'paymethod_select',
      'extra' => [
        'selected_payment_methods' => [$this->method->pmid => TRUE],
      ],
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
  public function tearDown() : void {
    node_delete($this->node->nid);
    if ($this->payment) {
      entity_delete('payment', $this->payment->pid);
    }
    entity_delete('payment_method', $this->method->pmid);
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
    $this->method->controller = $controller;
    $element = webform_component_invoke('paymethod_select', 'render', $this->node->webform['components'][1]);
    $form = [
      '#node' => $this->node,
      'submitted' => ['paymethod_select' => &$element],
    ];
    $form_state = [];
    $component->render($element, $form, $form_state);
    $form_state['storage']['page_num'] = 1;
    $form_state['storage']['submitted'] = [];
    $component->executeAjaxCallback($this->method, $form, $form_state);

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

  /**
   * Test invoking the AJAX callback with a form state on the wrong page.
   */
  public function testAjaxCallbackOnWrongPage() {
    $controller = $this->getMockBuilder(\PaymentMethodController::class)->getMock();
    $controller->expects($this->never())->method($this->anything());
    $method = entity_create('payment_method', [
      'controller' => $controller,
    ]);
    $component = new Component($this->node->webform['components'][1]);
    $element = webform_component_invoke('paymethod_select', 'render', $this->node->webform['components'][1]);
    $form = [
      '#node' => $this->node,
      'submitted' => ['paymethod_select' => $element],
    ];
    $form_state['storage']['page_num'] = 2;
    $component->render($element, $form, $form_state);
    $result = $component->executeAjaxCallback($method, $form, $form_state);
    $this->assertEqual([
      'code' => 400,
      'error' => 'Invalid form state.',
    ], $result);
    $this->assertEmpty($form['details']['sid']['#value'] ?? NULL);
  }

  /**
   * Test render with no methods available.
   */
  public function testRenderWithoutMethods() {
    $this->node->webform['components'][1]['extra']['selected_payment_methods'] = [];
    $component = new Component($this->node->webform['components'][1]);
    $element = webform_component_invoke('paymethod_select', 'render', $this->node->webform['components'][1]);
    $form = [
      '#node' => $this->node,
      'actions' => ['submit' => ['#type' => 'submit']],
      'submitted' => ['paymethod_select' => $element],
    ];
    $form_state['storage']['page_num'] = 2;
    $component->render($element, $form, $form_state);
    $this->assertNotEmpty($form['actions']['submit']['#disabled']);
  }

}
