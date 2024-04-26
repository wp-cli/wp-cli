<?php

namespace WP_CLI\Tests\CSV;

use WP_CLI\Tests\TestCase;
use WP_CLI\Iterators\CSV;

class CSVTest extends TestCase {

	public function test_it_can_iterate_over_a_csv_file() {
		$filename = $this->create_csv_file(
			array(
				array( 'foo', 'bar' ),
				array( 'baz', 'qux' ),
			)
		);

		$expected = array(
			0 => array(
				'foo' => 'baz',
				'bar' => 'qux',
			),
		);

		foreach ( new CSV( $filename ) as $index => $row ) {
			$this->assertEquals( $expected[ $index ], $row );
		}
	}

	public function test_it_can_iterate_over_a_csv_file_with_custom_delimiter() {
		$filename = $this->create_csv_file(
			array(
				array( 'foo|bar' ),
				array( 'baz|qux' ),
			),
			'|'
		);

		$expected = array(
			0 => array(
				'foo|bar' => 'baz|qux',
			),
		);

		foreach ( new CSV( $filename, '|' ) as $index => $row ) {
			$this->assertEquals( $expected[ $index ], $row );
		}
	}

	public function test_it_can_iterate_over_a_csv_file_with_multiple_lines_in_a_value() {
		$filename = $this->create_csv_file(
			array(
				array( 'foo', "bar\nbaz" ),
				array( 'qux', "quux\nquuz" ),
			)
		);

		$expected = array(
			0 => array(
				'foo'      => 'qux',
				"bar\nbaz" => "quux\nquuz",
			),
		);

		foreach ( new CSV( $filename ) as $index => $row ) {
			$this->assertEquals( $expected[ $index ], $row );
		}
	}

	public function test_it_can_iterate_over_a_csv_file_with_multiple_lines_and_comma_in_a_value() {
		$filename = $this->create_csv_file(
			array(
				array( 'foo', "bar\nbaz,qux" ),
				array( 'quux', "quuz\ncorge,grault" ),
			)
		);

		$expected = array(
			0 => array(
				'foo'          => 'quux',
				"bar\nbaz,qux" => "quuz\ncorge,grault",
			),
		);

		foreach ( new CSV( $filename ) as $index => $row ) {
			$this->assertEquals( $expected[ $index ], $row );
		}
	}

	private function create_csv_file( $data, $delimiter = ',' ) {
		$filename = tempnam( sys_get_temp_dir(), 'wp-cli-tests-' );

		$fp = fopen( $filename, 'wb' );

		foreach ( $data as $row ) {
			fputcsv( $fp, $row, $delimiter );
		}

		fclose( $fp );

		register_shutdown_function(
			function () use ( $filename ) {
				unlink( $filename );
			}
		);

		return $filename;
	}
}
