<?php

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

class SchemaValidationTest extends TestCase {
	/**
	 * Test validation of example YAML files against the JSON schema.
	 */
	public function testExampleYamlFilesValidateAgainstSchema(): void {
		$schemas_dir = dirname( __DIR__ ) . '/schemas';
		$schema_path = $schemas_dir . '/wp-cli-config.json';

		// Load schema once
		$schema_content = file_get_contents( $schema_path );
		$this->assertNotFalse( $schema_content, 'Schema file should be readable' );

		$schema = json_decode( $schema_content );
		$this->assertNotNull( $schema, 'Schema should be valid JSON' );

		// Find all .yml files in the schemas directory
		$yaml_files = glob( $schemas_dir . '/*.yml' );
		$this->assertNotFalse( $yaml_files, 'Should be able to glob for YAML files' );

		foreach ( $yaml_files as $yaml_file ) {
			// Load and parse the YAML file
			$yaml_content = file_get_contents( $yaml_file );
			$this->assertNotFalse( $yaml_content, 'YAML file should be readable: ' . basename( $yaml_file ) );

			$yaml_data = \Mustangostang\Spyc::YAMLLoadString( $yaml_content );
			$this->assertIsArray( $yaml_data, 'YAML should parse to an array/object: ' . basename( $yaml_file ) );

			// Convert YAML data to object for validation
			$json_string = json_encode( $yaml_data );
			$this->assertNotFalse( $json_string, 'YAML data should convert to JSON string: ' . basename( $yaml_file ) );

			$data = json_decode( $json_string );
			$this->assertNotNull( $data, 'YAML data should convert to JSON object: ' . basename( $yaml_file ) );

			// Validate using JSON Schema validator
			$validator = new Validator();
			$validator->validate( $data, $schema );

			$this->assertTrue(
				$validator->isValid(),
				$this->formatValidationErrors( basename( $yaml_file ), $validator->getErrors() )
			);
		}
	}

	/**
	 * Format validation errors into a readable message.
	 *
	 * @param string $filename The YAML filename being validated.
	 * @param array  $errors   Array of validation errors from JsonSchema\Validator.
	 * @return string Formatted error message.
	 */
	private function formatValidationErrors( string $filename, array $errors ): string {
		if ( empty( $errors ) ) {
			return "YAML file {$filename} should validate against schema.";
		}

		$message = "YAML file {$filename} failed schema validation:\n";

		foreach ( $errors as $error ) {
			$property = isset( $error['property'] ) ? $error['property'] : 'unknown';
			$pointer  = isset( $error['pointer'] ) ? $error['pointer'] : '';
			$msg      = isset( $error['message'] ) ? $error['message'] : 'Unknown error';

			$message .= sprintf(
				"  - Property '%s' (at %s): %s\n",
				$property,
				$pointer ?: 'root',
				$msg
			);
		}

		return rtrim( $message );
	}
}
