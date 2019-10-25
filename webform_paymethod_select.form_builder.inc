<?php
/**
 * @file
 * Form builder callbacks for the webform_paymethod select component.
 */

use Drupal\webform_paymethod_select\FormBuilderElement;

/**
 * Implements hook_form_builder_types().
 */
function webform_paymethod_select_form_builder_element_types($form_type, $form_id) {
  if ($form_type != 'webform') {
    return;
  }
  $map = _form_builder_webform_property_map('paymethod_select');
  unset($map['properties']['default_value']);
  $fields['paymethod_select'] = array(
    'class' => FormbuilderElement::class,
    'title' => t('Payment method'),
    'properties' => array_keys($map['properties']),
    'default' => array('#form_builder' => array('element_type' => 'paymethod_select')),
    'unique' => TRUE,
  );

  $fields['paymethod_select']['default'] += _form_builder_webform_default('paymethod_select');

  return $fields;

}

/**
 * Implements _webform_form_builder_map_<webform-component>().
 */
function _webform_form_builder_map_paymethod_select() {
  return [
    'form_builder_type' => 'paymethod_select',
  ];
}
