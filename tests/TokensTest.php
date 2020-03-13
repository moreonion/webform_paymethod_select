<?php

namespace Drupal\webform_paymethod_select;

use Upal\DrupalUnitTestCase;

/**
 * Test the token utility functions.
 */
class TokensTest extends DrupalUnitTestCase {

  /**
   * Test unprefixing tokens for webform components.
   */
  public function testUnprefixTokens() {
    $tokens = [
      'A:B' => '[A:B]',
      'A:C' => '[A:C]',
      'A:B:D' => '[A:B:D]',
      'E' => '[E]',
    ];
    $this->assertEqual([
      'B' => '[A:B]',
      'C' => '[A:C]',
      'B:D' => '[A:B:D]',
    ], Tokens::unprefixTokens($tokens, ['A']));
    $this->assertEqual([
      'D' => '[A:B:D]',
    ], Tokens::unprefixTokens($tokens, ['A', 'B']));
  }

}
