<?php 

DbMigrationCommand::add_migration_generator(
	BasicAuthMigration::$type, BasicAuthMigration::$name, array( 'BasicAuthMigration', 'generate' ));
				
DbMigrationCommand::add_migration(
	BasicAuthMigration::$type, BasicAuthMigration::$name, array( 'BasicAuthMigration', 'migrate' ));	

class BasicAuthMigration extends WP_CLI_Migration {
 
	public static $type = 'plugin';
	public static $name = 'basic-authentication';
	
	/**
	 * Generate current settings of a plugin
	 *
	 */
	function generate() {
		$data['options'] = array();
		
		$data['options']['basic_authentication_enabled'] =
			get_option( 'basic_authentication_enabled' )?"on":"off";
		
		$data['options']['basic_authentication_method'] =
			get_option( 'basic_authentication_method' );
		
		$data['options']['basic_authentication_password'] =
			get_option( 'basic_authentication_password' );
		
		return $data;
		
	} // function generate

	/**
	 * Import settings of a plugin
	 *
	 * @param array $data
	 */
	function migrate($data){

		// Process options, if any		
		if (array_key_exists('options', $data)) {
			
			if (array_key_exists('basic_authentication_enabled'
														, $data['options'])) {
				if ("on" == $data['options']['basic_authentication_enabled']) {
					update_option( 'basic_authentication_enabled',1);
				} else {
					// checkbox: option only exists when checked 'on'
					delete_option( 'basic_authentication_enabled');
				}
			}
		
			if (array_key_exists('basic_authentication_method'
														, $data['options'])) {
				update_option( 'basic_authentication_method', 
												$data['options']['basic_authentication_method']);		
			}
		
			if (array_key_exists('basic_authentication_password'
														, $data['options'])) {
				update_option( 'basic_authentication_password',
											$data['options']['basic_authentication_password']);
			}
			
		}
		
	} // function migrate
	
}