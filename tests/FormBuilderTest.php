<?php

namespace Drupal\webform_paymethod_select;

use Drupal\form_builder\Loader;
use Upal\DrupalUnitTestCase;

/**
 * Test component configuration via form builder.
 */
class FormBuilderTest extends DrupalUnitTestCase {

  /**
   * Prepare a test node.
   */
  public function setUp() : void {
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
    foreach ($node->webform['components'] as $cid => &$c) {
      $c['cid'] = $cid;
      webform_component_defaults($c);
    }
    node_save($node);
    $this->node = node_load($node->nid);
    $GLOBALS['conf']['webform_tracking_mode'] = '<none>';
  }

  /**
   * Remove the test node.
   */
  public function tearDown() : void {
    entity_delete('payment_method', $this->method->pmid);
    node_delete($this->node->nid);
    parent::tearDown();
  }

  /**
   * Test rendering the element configuration form.
   */
  public function testRenderConfigForm() {
    $form_obj = Loader::instance()->fromStorage('webform', $this->node->nid);
    $element_obj = $form_obj->getElement('cid_1');
    $form_state = form_state_defaults();
    $form_state['build_info']['form_id'] = 'form_builder_field_configure';
    $form_state['build_info']['args'] = ['webform', $this->node->nid];
    $form['#node'] = $this->node;
    $form = $element_obj->configurationForm($form, $form_state);
    $this->assertNotEmpty($form['line_items']);
  }

}
