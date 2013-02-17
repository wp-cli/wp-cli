<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext;

require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

require_once __DIR__ . '/../../php/utils.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface
{
	public $variables = array();

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct( array $parameters )
	{
		$this->runner = new WP_CLI_Command_Runner;
	}

	public function getStepDefinitionResources()
	{
		return array( __DIR__ . '/../steps/basic_steps.php' );
	}

	public function getHookDefinitionResources()
	{
		return array();
	}

	public function replace_variables( &$str )
	{
		$str = preg_replace_callback( '/\{(\w+)\}/', array( $this, '_replace_var' ), $str );
	}

	private function _replace_var( $matches )
	{
		$cmd = $matches[0];

		foreach ( array_slice( $matches, 1 ) as $key ) {
			$cmd = str_replace( '{' . $key . '}', $this->variables[ $key ], $cmd );
		}

		return $cmd;
	}
}

