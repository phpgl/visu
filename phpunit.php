<?php 
/**
 *---------------------------------------------------------------
 * Autoloader / Compser
 *---------------------------------------------------------------
 *
 * We need to access our dependencies & autloader..
 */
require __DIR__ . 
	DIRECTORY_SEPARATOR . 
	'vendor' . 
	DIRECTORY_SEPARATOR . 
	'autoload.php';

// some general paths
define('PATH_TEST_RESOURCES', __DIR__ . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'resources');
define('PATH_TEST_RES_SHADER', PATH_TEST_RESOURCES . DIRECTORY_SEPARATOR . 'shaders');