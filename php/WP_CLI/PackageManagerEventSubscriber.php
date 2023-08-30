<?php

namespace WP_CLI;

use Composer\DependencyResolver\Rule;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use WP_CLI;

/**
 * A Composer Event subscriber so we can keep track of what's happening inside Composer
 */
class PackageManagerEventSubscriber implements EventSubscriberInterface {

	/**
	 * Get subscribed events.
	 *
	 * @return array
	 */
	public static function getSubscribedEvents() {

		return [
			PackageEvents::PRE_PACKAGE_INSTALL  => 'pre_install',
			PackageEvents::POST_PACKAGE_INSTALL => 'post_install',
		];
	}

	/**
	 * Pre-install operation message.
	 *
	 * @param \Composer\Installer\PackageEvent $event
	 *
	 * @return void
	 */
	public static function pre_install( PackageEvent $event ) {
		$operation_message = $event->getOperation()->__toString();
		WP_CLI::log( ' - ' . $operation_message );
	}

	/**
	 * Post-install operation log.
	 *
	 * @param \Composer\Installer\PackageEvent $event
	 *
	 * @return void
	 */
	public static function post_install( PackageEvent $event ) {

		$operation = $event->getOperation();

		// getReason() was removed in Composer v2 without replacement.
		if ( ! method_exists( $operation, 'getReason' ) ) {
			return;
		}

		$reason = $operation->getReason();
		if ( $reason instanceof Rule ) {

			switch ( $reason->getReason() ) {

				case Rule::RULE_PACKAGE_CONFLICT:
				case Rule::RULE_PACKAGE_SAME_NAME:
				case Rule::RULE_PACKAGE_REQUIRES:
					$composer_error = $reason->getPrettyString( $event->getPool() );
					break;

			}

			if ( ! empty( $composer_error ) ) {
				WP_CLI::log( sprintf( ' - Warning: %s', $composer_error ) );
			}
		}
	}
}
