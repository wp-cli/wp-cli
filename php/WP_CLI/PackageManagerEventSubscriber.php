<?php

namespace WP_CLI;

use \Composer\DependencyResolver\Rule;
use \Composer\EventDispatcher\Event;
use \Composer\EventDispatcher\EventSubscriberInterface;
use \Composer\Installer\PackageEvent;
use \Composer\Script\ScriptEvents;
use \WP_CLI;

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
		$operation_message = $event->getOperation()->__toString();
		WP_CLI::log( ' - ' . $operation_message );
	}

	public static function post_install( PackageEvent $event ) {

		$operation = $event->getOperation();
		$reason = $operation->getReason();
		if ( $reason instanceof Rule ) {

			switch ( $reason->getReason() ) {

				case Rule::RULE_PACKAGE_CONFLICT;
				case Rule::RULE_PACKAGE_SAME_NAME:
				case Rule::RULE_PACKAGE_REQUIRES:
					$composer_error = $reason->getPrettyString( $event->getPool() );
					break;

			}

			if ( ! empty( $composer_error ) ) {
				WP_CLI::log( sprintf( " - Warning: %s", $composer_error ) );
			}
		}

	}

}
