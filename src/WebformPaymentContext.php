<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use \Drupal\little_helpers\Webform\Submission;
use \Drupal\little_helpers\Webform\Webform;
use \Drupal\payment_context\PaymentContextInterface;

class WebformPaymentContext implements PaymentContextInterface {
  protected $submission;
  protected $form_state;
  protected $component;
  protected $_cid;

  public function __construct($submission, &$form_state, &$component) {
    $this->submission = $submission;
    $this->component = &$component;
    $this->form_state = &$form_state;
  }

  public function name() { return 'webform_paymethod_select'; }

  /**
   * Serialize this object into an array for storing in the context_data column
   * of the payment table.
   */
  public function toContextData() {
    return $this->submission->ids() + array(
      'cid' => $this->component['cid'],
    );
  }

  public static function fromContextData($data) {
    if (($submission = Submission::load($data['nid'], $data['sid'])) &&
        ($component = $submission->webform->component($data['cid']))) {
      $form_state = NULL;
      return new static($submission, $form_state, $component);
    }
  }

  public function getSubmission() {
    return $this->submission;
  }

  /**
   * Return a path that can be used to re-enter the form if the payment failed.
   *
   * @return a link array
   */
  public function reenterLink(\Payment $payment) {
    $link['path'] = 'node/' . $this->submission->getNode()->nid;
    return $link;
  }

  public function getErrorUrl() {
    return NULL;
  }

  public function value($key) {
    return $this->submission->valueByKey($key);
  }

  public function valueByKeys(array $keys) {
    foreach ($keys as $k) {
      $v = $this->submission->valueByKey($k);
      if ($v) {
        return $v;
      }
    }
  }

  public function redirect($path, array $options = array()) {
    if ($this->form_state) {
      $this->form_state['redirect'] = array($path, $options);
    }
    else {
      drupal_goto($path, $options);
    }
  }

  public function redirectToStatus(\Payment $payment) {
    // Only redirect to the status page if we are not in the form submit process.
    if (!$this->form_state) {
      $page_num = $this->component['page_num'];
      drupal_set_message(t('Something went wrong during the payment process, please try again.'), 'error');
      $this->reenterWebform($page_num);
    }
  }

  protected function finishWebform() {
    $this->submission->is_draft = FALSE;
    $this->submission->save();
    $this->submission->sendEmails();
    $redirect = $this->submission->webform->getRedirect($this->submission);
    $this->redirect($redirect[0], $redirect[1]);
  }

  public function statusSuccess(\Payment $payment) {
    // If the webform submission is still in progress
    // webform_client_form_submit will set the proper redirect
    // otherwise we need to redirect immediately:
    if (!$this->form_state) {
      $page_num = $this->component['page_num'];
      $page_finished = TRUE;
      foreach ($this->submission->webform->componentsByType('paymethod_select') as $cid => $component) {
        if ($cid != $this->component['cid'] && $component['page_num'] == $page_num) {
          $component_finished = FALSE;
          if (($pid = $this->submission->valueByCid($cid)) && is_numeric($pid)) {
            if ($other_payment = entity_load_single('payment', $pid)) {
              $component_finished = payment_status_is_or_has_ancestor($other_payment->getStatus(), PAYMENT_STATUS_SUCCESS);
            }
          }
          if (!$component_finished) {
            $page_finished = FALSE;
            break;
          }
        }
      }

      if (!$page_finished) {
        drupal_set_message(t('Payment processed successfully.'));
        $this->reenterWebform($page_num);
      }
      else {
        $node = $this->submission->webform->node;
        $last_component = end($node->webform['components']);
        if ($page_num == $last_component['page_num']) {
          // @TODO: Store and check what the original form action was.
          //        Only call finishWebform if it wasn't a "save as draft".
          $this->finishWebform();
        }
        else {
          $this->reenterWebform($page_num + 1);
        }
      }
    }
  }

  public function reenterWebform($page_num) {
    $ids = $this->submission->ids();
    $options['query']['hash'] = _webform_paymethod_select_reenter_hash($ids['nid'], $ids['sid'], $page_num);
    $this->redirect("node/{$ids['nid']}/webform-continue/{$ids['sid']}/$page_num", $options);
  }
}
