<?php

namespace Drupal\webform_paymethod_select;

/**
 * Mark a payment method controller as being able to handle recurrent payments.
 *
 * @deprecated Starting with 2.0-alpha4 this interface is deprecated in favor
 *   of the payment_recurrent module. A migration path for existing payment
 *   methods can be found in the github issue below.
 * @see https://github.com/moreonion/webform_paymethod_select/issues/16
 */
interface PaymentRecurrentController {
}
