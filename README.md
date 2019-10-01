[![Build Status](https://travis-ci.com/moreonion/webform_paymethod_select.svg?branch=7.x-2.x)](https://travis-ci.com/moreonion/webform_paymethod_select) [![codecov](https://codecov.io/gh/moreonion/webform_paymethod_select/branch/7.x-2.x/graph/badge.svg)](https://codecov.io/gh/moreonion/webform_paymethod_select)

# Webform payment method selector

This module allows you to make [payments](https://www.drupal.org/project/payment) part of your [webforms](https://www.drupal.org/project/webform). It makes embedding payment into your forms (nearly) as easy as adding a new textarea.

## Features

-   **Seamless integration into webform**: The payment is handled like any other webform component. No-popups - redirects only if required by the chosen payment method.
-   **form_builder support**: You can use [form_builder](https://www.drupal.org/project/form_builder) to configure this webform component.
-   **Support for JavaScript-based payment methods** (PCI-SAQ A-EP) like [Stripe](https://www.drupal.org/project/stripe_payment) and [Braintree](https://www.drupal.org/project/braintree_payment).
-   **Re-entrance**: User can continue filling out the form after paying. They always land on the webform page again - even after being redirected off-site.

### Limitations
The module works only with payment methods that are aware of their [payment context](https://www.drupal.org/project/webform_paymethod_select).


## Usage
### Requirements
*as of version 7.x-2.0-beta3*

- PHP 7.0+ (as of version 2.0)
- [currency](https://www.drupal.org/project/currency)
- [jquery_update](https://www.drupal.org/project/jquery_update) for jQuery 1.7+.
- [little_helpers ≥ 2.0-alpha3](https://www.drupal.org/project/little_helpers) provides a nice API for accessing webform data.
- [payment ≥ 1.6](https://www.drupal.org/project/payment)
- [payment context](https://www.drupal.org/project/payment_context)
- [psr0](https://www.drupal.org/project/psr0) for class autoloading.
- [webform](https://www.drupal.org/project/webform)


### Configuration
-   Enable the module (ie. `drush en -y webform_paymethod_select`).
-   Configure your payment methods as usual at _admin/config/services/payment/method_.
-   Add this component to your webform (type _Payment Selector_).
-   Configure the component by filling the required fields.

### Advanced usage: Read values from other webform components
The following properties of your payment can be read from other webform components:

- The payment’s currency. Perhaps you also want to use [webform currency](https://www.drupal.org/project/webform_currency) in this case.
- The line item’s amount.
- The line item’s quantity.

*Take care that only valid values can be entered in the referenced component. No additional validations will be applied.*

### Advanced usage: Override values using special form keys
*added in 7.x-1.16*

You can also customize your payment line items for each form submission by using special form keys. Each of the keys must be prefixed with `payment__item{N}__` (the `{N}` references the n-th line item, starting with 1):

* `amount`: The line item amount (numeric value).
* `quantity`: The line item quantity (non-negative integer).
* `description`: The line item’s description (string).
* `tax_rate`: The line item’s tax rate (numeric value).
* `recurrence__interval_unit`: The base interval for recurrent payments.
* `recurrence__interval_value`: Multiplicator of the base interval.
* `recurrence__day_of_month`: Day of the month on which payment’s will be collected.
* `recurrence__month`: Month when the payment’s will be collected (ie. for yearly payments).
* `recurrence__start_date`: Earliest day for the first payment.
* `recurrence__count`: Stop recurrence after a specific number of payments.

For example a component with form-key `payment__item2__recurrence__interval_unit` with the value `monthly` will turn the second line item (if configured) into a monthly payment.

*Take care that only valid values can be entered in the referenced component. No additional validations will be applied.*

## Compatible payment methods

- [Braintree](https://www.drupal.org/project/braintree_payment)
- [GoCardless](https://www.drupal.org/project/gocardless_payment)
- [Manual direct debit](https://www.drupal.org/project/manual_direct_debit)
- [Paymill](https://www.drupal.org/project/paymill_payment)
- [PayOne](https://www.drupal.org/project/payone_payment)
- [SagePay](https://www.drupal.org/project/sagepay_payment)
- [Stripe](https://www.drupal.org/project/stripe_payment)

*Please post an issue if something is missing here*
