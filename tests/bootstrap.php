<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../subscribe-by-email.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

$_SERVER['SERVER_NAME'] = 'example.com';

require $_tests_dir . '/includes/bootstrap.php';	

class SBE_UnitTestCase extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		subscribe_by_email()->activate();
	}

	public function tearDown() {
		parent::tearDown();
		$model = incsub_sbe_get_model();
		$model->drop_schema();
	}
}

