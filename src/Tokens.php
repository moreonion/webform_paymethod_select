<?php

namespace Drupal\webform_paymethod_select;

/**
 * Token utility functions.
 */
abstract class Tokens {

  /**
   * Get all tokens for a webform component and return them the form_key prefix.
   */
  public static function unprefixTokens(array $tokens, array $parents) {
    $prefix = implode(':', $parents) . ':';
    $prefix_len = strlen($prefix);
    $matching_tokens = [];
    foreach ($tokens as $t => $original) {
      if (substr($t, 0, $prefix_len) == $prefix) {
        $matching_tokens[substr($t, $prefix_len)] = $original;
      }
    }
    return $matching_tokens;
  }

}
