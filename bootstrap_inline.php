<?php 
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
/**
 * ----------------------------------------------------------------------------
 * Paths
 * ----------------------------------------------------------------------------
 *
 * Make sure all required paths are registered
 */
if (!defined('VISU_PATH_ROOT')) define('VISU_PATH_ROOT', '' . __DIR__ . DS . '..' . DS . '..');
if (!defined('VISU_PATH_VENDOR')) define('VISU_PATH_VENDOR', VISU_PATH_ROOT . DS . 'vendor');

// Framework (VISU) paths
define('VISU_PATH_FRAMEWORK_ROOT', __DIR__);
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES')) define('VISU_PATH_FRAMEWORK_RESOURCES', VISU_PATH_FRAMEWORK_ROOT . DS . 'resources');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_SHADER')) define('VISU_PATH_FRAMEWORK_RESOURCES_SHADER', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'shader');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_FONT')) define('VISU_PATH_FRAMEWORK_RESOURCES_FONT', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'fonts');

// autoloader
require VISU_PATH_VENDOR . DS . 'autoload.php';