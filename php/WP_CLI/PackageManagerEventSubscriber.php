<?php

namespace WP_CLI;

use \Composer\DependencyResolver\Rule;
use \Composer\EventDispatcher\Event;
use \Composer\EventDispatcher\EventSubscriberInterface;
use \Composer\Script\PackageEvent;
use \Composer\Script\ScriptEvents;

/**
 * A Composer Event subscriber so we can keep track of what's happening inside Composer
 */
class PackageManagerEventSubscriber implements EventSubscriberInterface {

	public static function getSubscribedEvents() {

		return array(
			ScriptEvents::PRE_PACKAGE_INSTALL => 'pre_install',
			ScriptEvents::POST_PACKAGE_INSTALL => 'post_install',
			);
	}

	public static function pre_install( PackageEvent $event ) {
		\WP_CLI::line( 'Installing via Composer...' );
	}

	public static function post_install( PackageEvent $event ) {

		$operation = $event->getOperation();
		$reason = $operation->getReason();
		if ( $reason instanceof Rule ) {

			// switch ( $reason->getReason() ) {
			// 	case Rule::RULE_JOB_INSTALL:
			// 		$composer_error = 'Package required by root: ' . $reason->getRequiredPackage();
			// 		break;

			// 	case Rule::RULE_PACKAGE_REQUIRES:
			// 		$composer_error = $reason->getPrettyString();
			// 		break;
			// }

			\WP_CLI::line( sprintf( "Composer error: %s", $reason->getReason() ) );
		}

	}
	
}