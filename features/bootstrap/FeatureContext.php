<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

require_once __DIR__ . '/../../php/utils.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext
{
	private $variables = array();

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

	private function replace_variables( &$str )
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

	/**
	 * @Given /^an empty directory$/
	 */
	public function anEmptyDirectory()
	{
		$this->runner->create_empty_dir();
	}

	/**
	 * @Given /^WP files$/
	 */
	public function wordpressFiles()
	{
		$this->runner->download_wordpress_files();
	}

	/**
	 * @Given /^wp-config\.php$/
	 */
	public function wpConfigPhp()
	{
		$this->runner->create_config();
	}

	/**
	 * @Given /^a database$/
	 */
	public function aDatabase()
	{
		$this->runner->create_db();
	}

	/**
	 * @Given /^WP install$/
	 */
	public function wpInstall()
	{
		$this->runner->create_db();
		$this->runner->create_empty_dir();
		$this->runner->download_wordpress_files();
		$this->runner->create_config();
		$this->runner->run_install();
	}

	/**
	 * @Given /^custom wp-content directory$/
	 */
	public function customWpContentDirectory()
	{
		$this->runner->define_custom_wp_content_dir();
	}

	/**
	 * @When /^I run `(.+)`$/
	 */
	public function iRun( $cmd )
	{
		$cmd = ltrim( str_replace( 'wp', '', $cmd ) );

		$this->replace_variables( $cmd );

		$this->result = $this->runner->run( $cmd );
	}

	/**
	 * @When /^I run the previous command again$/
	 */
	public function iRunThePreviousCommandAgain()
	{
		if ( !isset( $this->result ) )
			throw new \Exception( 'No previous command.' );

		$this->result = $this->runner->run( $this->result->command );
	}

	/**
	 * @Given /^save (STDOUT|STDERR) as \{(\w+)\}$/
	 */
	public function saveStreamAsVariable( $stream, $key )
	{
		$this->variables[ $key ] = rtrim( $this->result->$stream, "\n" );
	}

	/**
	 * @Then /^the return code should be (\d+)$/
	 */
	public function theReturnCodeShouldBe( $return_code )
	{
		assertEquals( $return_code, $this->result->return_code );
	}

	/**
	 * @Then /^it should run without errors$/
	 */
	public function itShouldRunWithoutErrors()
	{
		if ( !empty( $this->result->STDERR ) )
			throw new \Exception( $this->result->STDERR );

		if ( 0 != $this->result->return_code )
			throw new \Exception( "Return code was $this->result->return_code" );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be:$/
	 */
	public function outputShouldBe( $stream, PyStringNode $output )
	{
		$this->replace_variables( $output );

		$result = rtrim( $this->result->$stream, "\n" );

		if ( (string) $output != $result ) {
			throw new \Exception( $this->result->$stream );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should contain:$/
	 */
	public function outputShouldContain( $stream, PyStringNode $output )
	{
		if ( false === strpos( $this->result->$stream, (string) $output ) ) {
			throw new \Exception( $this->result->$stream );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should match \'([^\']+)\'$/
	 */
	public function outputShouldMatch( $stream, $format )
	{
		assertStringMatchesFormat( $format, $this->result->$stream );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be empty$/
	 */
	public function outputShouldBeEmpty( $stream )
	{
		if ( !empty( $this->result->$stream ) ) {
			throw new \Exception( $this->result->$stream );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should not be empty$/
	 */
	public function outputShouldNotBeEmpty( $stream )
	{
		assertNotEmpty( rtrim( $this->result->$stream, "\n" ) );
	}

	/**
	 * @Then /^the (.+) file should exist$/
	 */
	public function fileShouldExist( $path )
	{
		assertFileExists( $this->runner->get_path( $path ) );
	}

	/**
	 * @Then /^database exists$/
	 */
	public function databaseExists()
	{
		throw new PendingException();
	}
}
