<?php

use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class WPVersionCompareTest extends TestCase {

	/**
	 * Test basic functionality
	 */
	public function testBasic() {
		$GLOBALS['wp_version'] = '4.9-alpha-40870-src';
		$this->assertTrue( Utils\wp_version_compare( '4.8', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.8', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-alpha-40870-src', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-alpha-40870-src', '=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-alpha-40870-src', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1-45000', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta2-46000', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9', '>=' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9', '<' ) );

		$GLOBALS['wp_version'] = '4.9-beta1-45000';
		$this->assertTrue( Utils\wp_version_compare( '4.8', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.8', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-alpha-40870-src', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1-45000', '>=' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1-45000', '=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1-45000', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta2-46000', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta2-46000', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9', '>=' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9', '<' ) );

		$GLOBALS['wp_version'] = '4.9-beta2-46000';
		$this->assertTrue( Utils\wp_version_compare( '4.8', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.8', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-alpha-40870-src', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-alpha-40870-src', '=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1-45000', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta2-45550', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta2-45550', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9', '>=' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9', '<' ) );

		$GLOBALS['wp_version'] = '4.9-rc1-47000';
		$this->assertTrue( Utils\wp_version_compare( '4.8', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.8', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-alpha-40870-src', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-alpha-40870-src', '=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1-45000', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta2-45550', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta2-45550', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-rc2', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-rc2-48000', '<' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9', '>=' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9', '<' ) );

		$GLOBALS['wp_version'] = '4.9';
		$this->assertTrue( Utils\wp_version_compare( '4.8', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.8', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-alpha-40870-src', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-alpha-40870-src', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1-45000', '>' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9-beta1', '<' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9-beta1', '>' ) );
		$this->assertTrue( Utils\wp_version_compare( '4.9', '>=' ) );
		$this->assertFalse( Utils\wp_version_compare( '4.9', '<' ) );
	}
}
