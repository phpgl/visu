<?php
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
set_time_limit(0);
/**
 *---------------------------------------------------------------
 * Autoloader / Composer
 *---------------------------------------------------------------
 *
 * We need to access our dependencies & autloader..
 */
require __DIR__ . DS . '..' . DS . 'vendor' . DS . 'autoload.php';


/**
 *---------------------------------------------------------------
 * Paths
 *---------------------------------------------------------------
 *
 * Setup paths needed in the application
 */
define('VISU_PATH_ROOT',         __DIR__);
define('VISU_PATH_CACHE',        VISU_PATH_ROOT . DS . '..' . DS . 'var' . DS . 'cache');
define('VISU_PATH_STORE',        VISU_PATH_ROOT . DS . '..' . DS . 'var' . DS . 'storage');
define('VISU_PATH_VENDOR',       VISU_PATH_ROOT . DS . '..' . DS . 'vendor');
define('VISU_PATH_RESOURCES',    VISU_PATH_ROOT . DS . 'resources');
define('VISU_PATH_APPCONFIG',    VISU_PATH_ROOT . DS . 'ctn');

define('VISU_APPCONFIG_ROOT',    '/examples.ctn');

/**
 *---------------------------------------------------------------
 * VISU
 *---------------------------------------------------------------
 *
 * Load the visu bootstrap file, which will create and return the
 * application container.
 * 
 * @var Container
 */
$container = require __DIR__ . '/../bootstrap.php';

// forward the container
return $container;