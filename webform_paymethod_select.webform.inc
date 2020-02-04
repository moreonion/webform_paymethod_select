<?php
/**
 * @file
 * Callbacks for the paymethod_select webform component.
 */

use Drupal\little_helpers\ArrayConfig;

/**
 * Implements _webform_defaults_[component]().
 */
function _webform_defaults_paymethod_select() {
  $element = array(
    'name' => 'Payment Method Selector',
    'form_key' => NULL,
    'mandatory' => 1,
    'value' => '',
    'pid' => 0,
    'weight' => 0,
    'extra' => array(
      'description' => '',
      'payment_description' => 'Default Payment',
      'selected_payment_methods' => array(),
      'line_items' => array(),
      'currency_code' => 'EUR',
      'currency_code_source' => 'fixed',
      'currency_code_component' => NULL,
      'private' => FALSE,
      'title_display' => 'above',
    ),
  );

  return $element;
}

/**
 * Helper function to get a list of all payment methods that have
 * been configured for this drupal installation
 */
function _webform_paymethod_select_payment_method_options() {
  // temporary payment object used just in this function
  $payment = entity_create('payment', array(
    'currency_code'   => 'EUR',
    'description'     => t('Default Payment'),
    'finish_callback' => 'webform_paymethod_select_payment_finish',
  ));

  $payment_method_options = array();

  foreach ($payment->availablePaymentMethods() as $pmid => $payment_method) {
    $payment_method_options[$pmid] = $payment_method->title_specific;
  }

  return $payment_method_options;
}


/**
 * Generate a list of currencies for use in a select form element.
 *
 * @return array
 *   Array of currencies keyed by their ISO-code.
 */
function _webform_paymethod_select_currency_options() {
  ctools_include('export');
  $options = array();
  foreach (currency_load_all() as $currency) {
    $options[$currency->ISO4217Code] = t('@currency_code - @currency_title', array(
      '@currency_title' => $currency->translateTitle(),
      '@currency_code' => $currency->ISO4217Code,
    ));
  }
  natcasesort($options);
  return $options;
}


/**
 * Implements _webform_edit_[component]().
 */
function _webform_edit_paymethod_select(array $component, array $original_form, array $form_state_copy) {
  ArrayConfig::mergeDefaults($component, _webform_defaults_paymethod_select());

  $form['extra']['payment_description'] = [
    '#type' => 'textfield',
    '#title' => t('Payment Description'),
    '#default_value' => $component['extra']['payment_description'],
    '#required' => TRUE,
    '#parents' => ['extra', 'payment_description'],
  ];

  $form['extra']['selected_payment_methods'] = array(
    '#title'         => t('Selected Payment Methods'),
    '#description'   => 'Select 1 or more payment methods that will be available for seletion to the user.',
    '#type'          => 'checkboxes',
    '#options'       => _webform_paymethod_select_payment_method_options(),
    '#default_value' => $component['extra']['selected_payment_methods'],
    '#parents' => ['extra', 'selected_payment_methods'],
  );

  $form['extra']['currency'] = array(
    '#type' => 'container',
  );

  $radio_id = drupal_html_id('webform-paymethod-select-currency');
  $form['extra']['currency']['source'] = array(
    '#title' => t('Currency'),
    '#attributes' => array('class' => array($radio_id)),
    '#type' => 'radios',
    '#options' => array(
      'fixed' => t('Use a fixed currency.'),
      'component' => t('Read the currency from another form element.'),
    ),
    '#default_value' => $component['extra']['currency_code_source'],
    '#parents' => ['extra', 'currency_code_source'],
  );

  $form['extra']['currency']['value'] = array(
    '#description'   => t('Select the currency for this payment.'),
    '#type'          => 'select',
    '#options'       => _webform_paymethod_select_currency_options(),
    '#default_value' => $component['extra']['currency_code'],
    '#states'        => array(
      'visible' => array(".$radio_id" => array('value' => 'fixed')),
    ),
    '#wrapper_attributes' => array(
      'class' => array('webform-paymethod-select-component-or-value-option'),
    ),
    '#parents' => ['extra', 'currency_code'],
  );

  $other_components = _webform_paymethod_select_other_components($original_form, $form_state_copy);
  $form['extra']['currency']['component'] = array(
    '#default_value' => $component['extra']['currency_code_component'],
    '#states' => array(
      'visible' => array(".$radio_id" => array('value' => 'component')),
    ),
    '#wrapper_attributes' => array(
      'class' => array('webform-paymethod-select-component-or-value-option'),
    ),
    '#parents' => ['extra', 'currency_code_component'],
  ) + _webform_paymethod_select_other_component_selector($other_components);

  $form['extra']['line_items'] = array(
    '#type' => 'fieldset',
    '#title' => t('Line Items'),
  );

  $form['extra']['line_items']['items'] = array(
    '#title'         => t('Line items'),
    '#description'   => t('Define 1 or more line items.'),
    '#type'          => 'payment_line_item',
    '#cardinality'   => 0,
    '#default_value' => $component['extra']['line_items'],
    '#required'      => TRUE,
    '#currency_code' => $component['extra']['currency_code'],
    '#parents' => ['extra', 'line_items'],
    '#other_components' => $other_components,
  );

  return $form;
}

/**
 * Get a list of all other components in this webform.
 *
 * @return string[]
 *   Component names keyed by cid or form_builder element ID.
 */
function _webform_paymethod_select_other_components(array $form, array &$form_state) {
  module_load_include('inc', 'webform', 'includes/webform.components');

  if (isset($form_state['webform_paymethod_select_other_components'])) {
    $other_components = $form_state['webform_paymethod_select_other_components'];
  }
  else {
    $node = webform_paymethod_select_get_node($form, $form_state);
    $other_components = webform_component_list($node, FALSE);
    foreach ($other_components as $cid => $component) {
      if ($node->webform['components'][$cid]['type'] == 'paymethod_select') {
        unset($other_components[$cid]);
      }
    }
    $form_state['webform_paymethod_select_other_components'] = $other_components;
  }
  return $other_components;
}

/**
 * Generate a component select form element for selecting other components.
 *
 * @return array
 *   Form-API select element.
 */
function _webform_paymethod_select_other_component_selector(array $other_components) {
  $components_disabled = empty($other_components);
  if ($components_disabled) {
    $other_components = array('' => t('No available components'));
  }

  return array(
    '#type' => 'select',
    '#title' => t('Component'),
    '#title_display' => 'invisible',
    '#options' => $other_components,
    '#disabled' => $components_disabled,
  );
}

/**
 * Implements _webform_render_[component]().
 */
function _webform_render_paymethod_select($component, $value = NULL, $filter = TRUE) {
  $defaults = _webform_defaults_paymethod_select();
  $component += $defaults;
  $component['extra'] += $defaults['extra'];

  $node = isset($component['nid']) ? node_load($component['nid']) : NULL;

  $element = array(
    '#type' => 'container',
    '#theme' => 'webform_paymethod_select_placeholder',
    '#theme_wrappers' => ['webform_element'],
    '#title' => $filter ? webform_filter_xss($component['name']) : $component['name'],
    '#title_display' => $component['extra']['title_display'] ? $component['extra']['title_display'] : 'before',
    '#description' => $filter ? webform_filter_descriptions($component['extra']['description'], $node) : $component['extra']['description'],
    '#required' => TRUE,
    '#weight' => isset($component['weight']) == TRUE ? $component['weight'] : 0,
    '#selected_payment_methods' => $component['extra']['selected_payment_methods'],
    '#currency_code' => $component['extra']['currency_code'],
    '#line_items' => array('items' => $component['extra']['line_items']),
    '#payment_description' => $component['extra']['payment_description'],
    '#value' => $value,
  );

  return $element;
}
