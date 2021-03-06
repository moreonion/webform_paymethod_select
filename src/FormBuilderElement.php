<?php

namespace Drupal\webform_paymethod_select;

use Drupal\form_builder_webform\Element;
use Drupal\little_helpers\ArrayConfig;

/**
 * Form builder integration for the paymethod select webform component.
 */
class FormBuilderElement extends Element {

  /**
   * Generate the component edit form for this component.
   *
   * @param array $component
   *   The webform componenent array.
   *
   * @return array
   *   Form-API array of the component edit form.
   */
  protected function componentEditForm($component) {
    $component = $this->element['#webform_component'];
    $form_id = 'webform_component_edit_form';
    $form_state = form_state_defaults();
    $nid = isset($component['nid']) ? $component['nid'] : NULL;
    $node = !isset($nid) ? (object) array('nid' => NULL, 'webform' => webform_node_defaults()) : node_load($nid);

    // The full node is needed here so that the "private" option can be access
    // checked.
    $form_state['webform_paymethod_select_other_components'] = $this->otherComponents($node);
    $form = $form_id([], $form_state, $node, $component);
    // We want to avoid a full drupal_get_form() for now but some alter hooks
    // need defaults normally set in drupal_prepare_form().
    $form += ['#submit' => []];
    $form_state['build_info']['args'][1] = $component;
    drupal_alter(['form', 'form_webform_component_edit_form'], $form, $form_state, $form_id);
    return $form;
  }

  /**
   * Get all the other components in this form.
   */
  protected function otherComponents($node) {
    $components = $this->form->getComponents($node);
    $other_components = [];
    foreach ($components as $cid => $component) {
      if (in_array($component['type'], ['pagebreak', 'fieldset'])) {
        continue;
      }
      $other_components[$component['form_builder_element_id']] = $component['name'];
    }
    unset($other_components[$this->element['#form_builder']['element_id']]);
    return $other_components;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationForm($form, &$form_state) {
    $form = parent::configurationForm($form, $form_state);
    // Only top-level elements can be assigned to property groups.
    // @see form_builder_field_configure_pre_render()
    $edit = $this->componentEditForm($this->element['#webform_component']);
    $group['#form_builder']['property_group'] = 'options';
    $form['payment_description'] = $edit['extra']['payment_description'] + $group;
    $form['selected_payment_methods'] = $edit['extra']['selected_payment_methods'] + $group;
    $form['currency'] = $edit['extra']['currency'] + $group;
    $form['line_items'] = $edit['extra']['line_items'] + $group;
    return $form;
  }

  /**
   * Store component configuration just like webform would do it.
   *
   * The values are already at their proper places in `$form_state['values']`
   * because the `#parents` array is provided in `_webform_edit_paymethod_select()`.
   */
  public function configurationSubmit(&$form, &$form_state) {
    $component = $form_state['values'];
    ArrayConfig::mergeDefaults($component, $this->element['#webform_component']);
    $this->element['#webform_component'] = $component;
    parent::configurationSubmit($form, $form_state);
  }

}
