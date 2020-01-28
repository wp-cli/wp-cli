<?php

namespace WP_CLI\Compat;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound,Generic.Classes.DuplicateClassName.Found

if ( PHP_VERSION_ID >= 50600 ) {
	require_once __DIR__ . '/Min_PHP_5_6/FeedbackMethodTrait.php';

	trait FeedbackMethodTrait {

		use Min_PHP_5_6\FeedbackMethodTrait;
	}

	return;
}

require_once __DIR__ . '/Min_PHP_5_4/FeedbackMethodTrait.php';

trait FeedbackMethodTrait {

	use Min_PHP_5_4\FeedbackMethodTrait;
}
