<?php
/**
 * @file
 */

use Drupal\webform_paymethod_select\PaymethodLineItem;

/**
 * Implements hook_element_info_alter().
 */
function webform_paymethod_select_element_info_alter(&$element) {
  if (isset($element['payment_line_item']) == TRUE) {
    $element['payment_line_item']['#process'][]        = 'webform_paymethod_select_element_process_line_item';
    $element['payment_line_item']['#element_validate'] = array('webform_paymethod_select_element_validate_line_item');
  }
}

/**
 * Callback function set in hook_element_info_alter() hook
 */
function webform_paymethod_select_element_process_line_item(array $element, array &$form_state, array $form) {
  $parents = isset($element['#parents']) ? $element['#parents'] : array('extra', 'line_items', 'items');

  foreach($element as $key => &$line_element) {

    if (strpos($key, 'container_') === 0) {
      $index = str_replace('container_', '', $key);
      $defaults = NULL;
      if (   isset($element['#default_value'][$index]) == TRUE
          && is_object($element['#default_value'][$index]) == TRUE) {
        $defaults = $element['#default_value'][$index];
      }
      else {
        $defaults = new PaymethodLineItem(array(
          'amount' => '',
          'amount_source' => 'fixed',
          'quantity' => 1,
          'quantity_source' => 'fixed',
          'tax_rate' => 0,
        ));
      }

      $form_builder = isset($form_state['webform_paymethod_select_other_components']);
      if ($form_builder && is_numeric($defaults->amount_component)) {
        $defaults->amount_component = 'cid_' . $defaults->amount_component;
      }
      if ($form_builder && is_numeric($defaults->quantity_component)) {
        $defaults->quantity_component = 'cid_' . $defaults->quantity_component;
      }

      $radio_id = drupal_html_id('webform-paymethod-select-' . $key . '-amount-source');
      $line_element['amount'] = array(
        '#type' => 'container',
        '#weight' => -9,
      );

      $line_element['amount']['source'] = array(
        '#title' => t('Amount'),
        '#attributes' => array('class' => array($radio_id)),
        '#type' => 'radios',
        '#options' => array(
          'fixed' => t('Set fixed amount'),
          'component' => t('Read the amount from another form element.'),
        ),
        '#default_value' => $defaults->amount_source,
      );

      $line_element['amount']['value'] = array(
        '#type'          => 'textfield',
        '#size'          => '20',
        '#maxlength'     => '20',
        '#required'      => FALSE,
        '#default_value' => $defaults->amount,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
        ),
        '#states'        => array(
          'visible' => array(".$radio_id" => array('value' => 'fixed')),
        ),
      );

      $line_element['amount']['component'] = array(
        '#default_value' => $defaults->amount_component,
        '#required'      => FALSE,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
          ),
        '#states'        => array(
          'visible' => array(".$radio_id" => array('value' => 'component')),
        ),
      ) + _webform_paymethod_select_other_component_selector($form, $form_state);

      $line_element['tax_rate']['#wrapper_attributes'] = array(
        'class' => array('webform-paymethod-select-line-item-tax-rate'),
      );

      $radio_quantity = drupal_html_id('webform-paymethod-select-' . $key . '-quantity_source');
      $line_element['quantity'] = array(
        '#type' => 'container',
        '#weight' => -8,
      );
      $line_element['quantity']['source'] = array(
        '#title' => t('Quantity'),
        '#attributes'    => array('class' => array($radio_quantity)),
        '#type'          => 'radios',
        '#options' => array(
          'fixed' => t('Set fixed quantity'),
          'component' => t('Get quantity from another form element'),
        ),
        '#default_value' => $defaults->quantity_source,
      );

      $line_element['quantity']['value'] = array(
        '#type'          => 'textfield',
        '#size'          => '20',
        '#maxlength'     => '20',
        '#required'      => FALSE,
        '#default_value' => $defaults->quantity,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
        ),
        '#states'        => array(
          'visible' => array(".$radio_quantity" => array('value' => 'fixed')),
        ),
      );
      $line_element['quantity']['component'] = array(
        '#default_value' => $defaults->quantity_component,
        '#required'      => FALSE,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
          ),
        '#states'        => array(
          'visible' => array(".$radio_quantity" => array('value' => 'component')),
        ),
      ) + _webform_paymethod_select_other_component_selector($form, $form_state);
    }
  }
  return $element;
}

/**
 * Callback function set in hook_element_info_alter() hook
 */
function webform_paymethod_select_element_validate_line_item(array $element, array &$form_state) {
  $line_items = [];
  foreach (element_children($element) as $key) {
    $container = $element[$key];

    // Skip empty line items
    if (empty($container['tax_rate']['#value']) && empty($container['description']['#value'])) {
      continue;
    }

    $data['quantity_source'] = $container['quantity']['source']['#value'];
    $data['quantity_component'] = NULL;
    if ($data['quantity_source'] == 'fixed') {
      $v = $container['quantity']['value']['#value'];
      // They're not all empty, so make sure they all contain input.
      if ((int) $v == $v && $v >= 0) {
        $data['quantity'] = (int) $v;
      }
      else {
        form_error($container['quantity']['value'], t('Quantity needs to be a non-negative number.'));
      }
    }
    else {
      $data['quantity_component'] = $container['quantity']['component']['#value'];
      $data['quantity'] = NULL;
    }

    $v = $container['tax_rate']['#value'];
    if (is_numeric($v) || $v >= 0) {
      $data['tax_rate'] = (float) $v / 100;
    }
    else {
      form_error($container['tax_rate'], 'Tax rate must be a positive percentage.');
    }

    if (empty($container['description']['#value'])) {
      form_error($container['description'], t('The line item description must not be empty.'));
    }
    else {
      $data['description'] = $container['description']['#value'];
    }
    $data['name'] = $container['name']['#value'];

    $data['amount_source'] = $container['amount']['source']['#value'];
    $data['amount_component'] = NULL;
    if ($data['amount_source'] == 'fixed') {
      $v = $container['amount']['value']['#value'];
      if (is_numeric($v) && $v >= 0) {
        $data['amount'] = (float) $v;
      }
      else {
        form_error($container['amount']['value'], 'Amount must be a positive number or 0.');
      }
    } else {
      $data['amount_component'] = $container['amount']['component']['#value'];
      $data['amount'] = NULL;
    }

    // Convert the raw input to a PaymentLineItem object.
    $line_items[] = new PaymethodLineItem($data);
  }
  form_set_value($element, $line_items, $form_state);
}

/**
 * Implements hook_webform_component_presave().
 *
 * We use a fieldset to put the line_items inside in the webform component
 * edit form; but the payment module expects the line_item data to not be
 * nested inside a fieldset -> removing 1 nesting level here
 */
function webform_paymethod_select_webform_component_presave(&$component) {
  if (isset($component['extra']['line_items']['items'])) {
    $line_items = &$component['extra']['line_items']['items'];
    unset($component['extra']['line_items']['items']);
    $component['extra']['line_items'] = &$line_items;
  }
}
