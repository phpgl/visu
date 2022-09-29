<?php 
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
/**
 *---------------------------------------------------------------
 * Autoloader / Compser
 *---------------------------------------------------------------
 *
 * We need to access our dependencies & autloader..
 */
require __DIR__ . 
	DS . 
	'vendor' . 
	DS . 
	'autoload.php';

// main paths
define('VISU_PATH_ROOT', __DIR__ . DS . 'tests_env');
define('VISU_PATH_CACHE', VISU_PATH_ROOT . DS . 'var' . DS . 'cache');
define('VISU_PATH_STORE', VISU_PATH_ROOT . DS . 'var' . DS . 'store');
define('VISU_PATH_APPCONFIG', VISU_PATH_ROOT . DS . 'app');

// some general paths
define('PATH_TEST_RESOURCES', __DIR__ . DS . 'tests' . DS . 'resources');
define('VISU_PATH_RESOURCES', PATH_TEST_RESOURCES);
define('PATH_TEST_RES_SHADER', PATH_TEST_RESOURCES . DS . 'shaders');

// framework paths
define('VISU_PATH_FRAMEWORK_ROOT', __DIR__);
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES')) define('VISU_PATH_FRAMEWORK_RESOURCES', VISU_PATH_FRAMEWORK_ROOT . DS . 'resources');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_SHADER')) define('VISU_PATH_FRAMEWORK_RESOURCES_SHADER', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'shader');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_FONT')) define('VISU_PATH_FRAMEWORK_RESOURCES_FONT', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'fonts');