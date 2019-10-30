<?php

namespace Drupal\webform_paymethod_select;

use Upal\DrupalUnitTestCase;

/**
 * Test the form builder element id to cid conversion when saving a webform.
 */
class ElementIdToCidTest extends DrupalUnitTestCase {

  /**
   * Load webform component defaults.
   */
  protected function webformDefaults($node) {
    module_load_include('components.inc', 'webform', 'includes/webform');
    foreach ($node->webform['components'] as $cid => &$c) {
      $c['cid'] = $cid;
      webform_component_defaults($c);
    }
  }

  /**
   * Prepare a node stub with a certain set of components.
   */
  protected function nodeWithComponents(array $components) {
    $node_a['nid'] = 1;
    $node_a['webform']['components'] = $components;
    $node = (object) $node_a;
    $this->webformDefaults($node);
    return $node;
  }

  /**
   * Test converting element id to cid.
   */
  public function testConversion() {
    $components[1] = [
      'type' => 'number',
      'cid' => 1,
      'form_key' => 'amount_source',
      'form_builder_element_id' => 'new_1234',
    ];
    $components[2] = [
      'type' => 'paymethod_select',
      'extra' => [
        'line_items' => [
          new PaymethodLineItem([
            'amount_component' => 'new_1234',
          ]),
        ],
      ],
      'form_builder_element_id' => 'cid_2',
    ];
    $node = $this->nodeWithComponents($components);
    webform_paymethod_select_node_presave($node);
    $this->assertEqual(1, $node->webform['components'][2]['extra']['line_items'][0]->amount_component);
  }

  /**
   * Test node save that was not triggered by form_builder.
   */
  public function testConversionNonFormBuilder() {
    $components[1] = [
      'type' => 'number',
      'cid' => 1,
      'form_key' => 'amount_source',
    ];
    $components[2] = [
      'type' => 'paymethod_select',
      'extra' => [
        'line_items' => [
          new PaymethodLineItem([
            'amount_component' => 1,
          ]),
        ],
      ],
    ];
    $node = $this->nodeWithComponents($components);
    webform_paymethod_select_node_presave($node);
    $this->assertEqual(1, $node->webform['components'][2]['extra']['line_items'][0]->amount_component);
  }

}
