<?php

use PHPUnit\Framework\TestCase;
use WP_CLI\Formatter;

/**
 * Tests for WP_CLI\Formatter related to YAML output.
 */
class FormatterTest extends TestCase {

    public function test_yaml_outputs_scalar_zero() {
        $assoc_args = [
            'format' => 'yaml',
            'fields' => ['value'],
        ];

        $formatter = new Formatter( $assoc_args );
        ob_start();
        $formatter->display_items( [ [ 'value' => 0 ] ] );
        $output = ob_get_clean();

        $this->assertStringContainsString("value: 0", $output, "YAML scalar 0 should be displayed correctly.");
    }

    public function test_yaml_outputs_string_value() {
        $assoc_args = [
            'format' => 'yaml',
            'fields' => ['value'],
        ];

        $formatter = new Formatter( $assoc_args );
        ob_start();
        $formatter->display_items( [ [ 'value' => 'hello' ] ] );
        $output = ob_get_clean();

        $this->assertStringContainsString("value: hello", $output, "YAML string should be displayed correctly.");
    }

    public function test_print_value_scalar_zero() {
        ob_start();
        \WP_CLI::print_value( 0, [ 'format' => 'yaml' ] );
        $output = ob_get_clean();

        $this->assertStringContainsString("---\n0", $output, "Direct WP_CLI::print_value should render scalar 0 correctly.");
    }
}

