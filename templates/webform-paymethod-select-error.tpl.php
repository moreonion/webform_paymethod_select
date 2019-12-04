<?php

/**
 * @file
 * Render an error message for previous payment attempts.
 *
 * Available variables:
 * - $payment: The payment that’s being processed.
 * - $status: The current payment status.
 * - $status_title: The title of the current status.
 */
?>
<?php echo t('The previous payment attempt seems to have failed. The current payment status is "!status_title". Please try again!', ['!status_title' => $status_title]); ?>
