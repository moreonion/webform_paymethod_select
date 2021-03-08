<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Submission;
use Upal\DrupalUnitTestCase;

/**
 * Test for form handling functions.
 */
class FormTest extends DrupalUnitTestCase {

  /**
   * Create a test node.
   */
  public function setUp() : void {
    parent::setUp();
    module_load_include('inc', 'webform', 'includes/webform.components');
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
    $this->node = $node;

    $GLOBALS['conf']['webform_tracking_mode'] = 'none';
  }

  /**
   * Delete the test node.
   */
  public function tearDown() : void {
    node_delete($this->node->nid);
    parent::tearDown();
  }

  /**
   * Test submitting a form step with one successful payment.
   */
  public function testSubmitSuccess() {

    $form_state['clicked_button']['#parents'] = ['next'];
    $form_state['values']['op'] = 'next';
    $form_state['webform_completed'] = TRUE;
    $form_state['webform_paymethod_select']['pages'][1] = [1];
    $form_state['webform_paymethod_select'][1] = $component = $this->createMock(Component::class);
    $component->method('value')->willReturn([]);
    $component->expects($this->once())->method('submit');
    $form_state['webform']['page_num'] = 1;
    $form['#node'] = $this->node;
    $form_state['values']['submitted'][1] = ['payment' => 'data'];
    $form_state['values']['submitted'][2] = ['test@example.com'];
    $form_state['values']['details']['sid'] = NULL;
    webform_paymethod_select_form_submit($form, $form_state);

    // Values have been overridden by the component.
    $this->assertEqual([], $form_state['values']['submitted'][1]);

    // A webform submission was created.
    $this->assertNotEmpty($form_state['values']['details']['sid']);

    $submission = Submission::load($this->node->nid, $form_state['values']['details']['sid']);
    $this->assertEqual([''], $submission->data[1]);
  }

}
