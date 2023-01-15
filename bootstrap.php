<?php 
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }
/**
 * ----------------------------------------------------------------------------
 * Paths
 * ----------------------------------------------------------------------------
 *
 * Make sure all required paths are registered
 */
if (!defined('VISU_PATH_ROOT')) die('VISU_PATH_ROOT needs to be specified.');
if (!defined('VISU_PATH_CACHE')) die('VISU_PATH_CACHE needs to be specified.');
if (!defined('VISU_PATH_STORE')) die('VISU_PATH_STORE needs to be specified.');
if (!defined('VISU_PATH_RESOURCES')) die('VISU_PATH_RESOURCES needs to be specified.');
if (!defined('VISU_PATH_RESOURCES_SHADER')) define('VISU_PATH_RESOURCES_SHADER', VISU_PATH_RESOURCES . DS . 'shader');
if (!defined('VISU_PATH_APPCONFIG')) die('VISU_PATH_APPCONFIG needs to be specified.');
if (!defined('VISU_PATH_VENDOR')) define('VISU_PATH_VENDOR', VISU_PATH_ROOT . DS . 'vendor');

// Framework (VISU) paths
define('VISU_PATH_FRAMEWORK_ROOT', __DIR__);
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES')) define('VISU_PATH_FRAMEWORK_RESOURCES', VISU_PATH_FRAMEWORK_ROOT . DS . 'resources');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_SHADER')) define('VISU_PATH_FRAMEWORK_RESOURCES_SHADER', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'shader');
if (!defined('VISU_PATH_FRAMEWORK_RESOURCES_FONT')) define('VISU_PATH_FRAMEWORK_RESOURCES_FONT', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'fonts');

/**
 * ----------------------------------------------------------------------------
 * Global configuration
 * ----------------------------------------------------------------------------
 *
 * Configuration values that should not influence testabilty
 * and should never change during application runtime.
 */
if (!defined('VISU_APPCONFIG_PREFIX')) define('VISU_APPCONFIG_PREFIX', 'app');
if (!defined('VISU_APPCONFIG_ROOT')) define('VISU_APPCONFIG_ROOT', '/app.ctn');

/**
 * ----------------------------------------------------------------------------
 * Setup the Container
 * ----------------------------------------------------------------------------
 *
 * We need to access our dependencies & autloader..
 */
$factory = new \ClanCats\Container\ContainerFactory(VISU_PATH_CACHE);

$container = $factory->create('GameContainer', function($builder)
{
    // ensure var directory with cache and store exists
    if (!file_exists(VISU_PATH_CACHE)) mkdir(VISU_PATH_CACHE, 0777, true);
    if (!file_exists(VISU_PATH_STORE)) mkdir(VISU_PATH_STORE, 0777, true);

    $importPaths = [
        'app' => VISU_PATH_ROOT . VISU_APPCONFIG_ROOT,
    ];

    global $overrideVisuBaseImportPaths;
    if (is_array($overrideVisuBaseImportPaths)) {
        foreach($overrideVisuBaseImportPaths as $name => $path) {
            $importPaths[$name] = $path;
        }
    }

    // create a new container file namespace and parse our `app.ctn` file.
    $namespace = new \ClanCats\Container\ContainerNamespace($importPaths);
    $namespace->importDirectory(VISU_PATH_APPCONFIG, VISU_APPCONFIG_PREFIX);
    $namespace->importFromVendor(VISU_PATH_VENDOR);

    // start with visu
    $namespace->parse(__DIR__ . '/visu.ctn');

    // import the namespace data into the builder
    $builder->importNamespace($namespace);
});

/**
 * Set requried defaults
 */
$container->setParameter('env', $container->getParameter('env', 'undefined'));
$container->setParameter('debug', $container->getParameter('debug', false));

// also add some paths to the container
$container->setParameter('visu.path.resources.shader', $container->getParameter('visu.path.resources.shader', VISU_PATH_RESOURCES_SHADER));
$container->setParameter('visu.path.framework.resources.shader', $container->getParameter('visu.path.framework.resources.shader', VISU_PATH_FRAMEWORK_RESOURCES_SHADER));

/**
 * Build Bootstrap Signal
 */
$signal = new VISU\Signals\BootstrapSignal($container);

// dispatch the pre bootstrap signal
$container->get('visu.dispatcher')->dispatch('bootstrap.pre', $signal);




// dispatch the pre bootstrap signal
$container->get('visu.dispatcher')->dispatch('bootstrap.post', $signal);

// return the container
return $container;
