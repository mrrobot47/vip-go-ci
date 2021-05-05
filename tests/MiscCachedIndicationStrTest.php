<?php

namespace Vipgoci\tests;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class MiscCachedIndicationStrTest extends TestCase {
	/**
	 * @covers ::vipgoci_cached_indication_str
	 */
	public function testCachedIndicationStr1() {
		$this->assertsame(
			' (cached)',
			vipgoci_cached_indication_str(
				true
			)
		);

		$this->assertsame(
			' (cached)',
			vipgoci_cached_indication_str(
				array( 1, 2, 3 ),
			)
		);

		$this->assertsame(
			'',
			vipgoci_cached_indication_str(
				false,
			)
		);
	}
}
