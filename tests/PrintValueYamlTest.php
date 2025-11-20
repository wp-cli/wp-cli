<?php

namespace WP_CLI\Tests;

use WP_CLI;

class PrintValueYamlTest extends TestCase {

    public function testPrintValueYamlEmptyString() {
        $value = '';
        $args  = [ 'format' => 'yaml' ];

        ob_start();
        WP_CLI::print_value( $value, $args );
        $output = trim( ob_get_clean() );

        $this->assertSame("---\n\"\"", $output);
    }

    public function testPrintValueYamlNull() {
        $value = null;
        $args  = [ 'format' => 'yaml' ];

        ob_start();
        WP_CLI::print_value( $value, $args );
        $output = trim( ob_get_clean() );

        $this->assertSame("---\nnull", $output);
    }

    public function testPrintValueYamlScalar() {
        $value = 42;
        $args  = [ 'format' => 'yaml' ];

        ob_start();
        WP_CLI::print_value( $value, $args );
        $output = trim( ob_get_clean() );

        $this->assertSame("---\n42", $output);
    }
}
