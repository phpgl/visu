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
 * Ensure required directories exist
 * ----------------------------------------------------------------------------
 *
 * When VISU is required as a dependency in a new project, these directories
 * may not exist yet. Create them early so the container factory and all
 * downstream code can rely on their presence.
 */
foreach ([
    VISU_PATH_CACHE,
    VISU_PATH_STORE,
    VISU_PATH_RESOURCES,
    VISU_PATH_RESOURCES_SHADER,
    VISU_PATH_APPCONFIG,
] as $requiredDir) {
    if (!is_dir($requiredDir)) {
        mkdir($requiredDir, 0777, true);
    }
}

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

    // Ensure app.ctn exists in the project root. When VISU is used as an engine
    // dependency, projects may not have created one yet — create an empty one so
    // the container namespace can resolve the `import app` in visu.ctn.
    $appCtnFile = VISU_PATH_ROOT . VISU_APPCONFIG_ROOT;
    if (!file_exists($appCtnFile)) {
        file_put_contents($appCtnFile, "/**\n * VISU application container configuration.\n * @see https://container.clancats.com/\n */\n");
    }

    $importPaths = [
        'app' => $appCtnFile,
    ];

    global $overrideVisuBaseImportPaths;
    if (is_array($overrideVisuBaseImportPaths)) {
        foreach($overrideVisuBaseImportPaths as $name => $path) {
            $importPaths[$name] = $path;
        }
    }

    // create a new container file namespace and parse our `app.ctn` file.
    $namespace = new \ClanCats\Container\ContainerNamespace($importPaths);
    if (is_dir(VISU_PATH_APPCONFIG)) {
        $namespace->importDirectory(VISU_PATH_APPCONFIG, VISU_APPCONFIG_PREFIX);
    }
    // Generate an empty container map if it doesn't exist yet.
    // This happens when VISU is used as an engine dependency and the consumer
    // project hasn't run the Composer post-autoload-dump script.
    $containerMapFile = VISU_PATH_VENDOR . '/container_map.php';
    if (!file_exists($containerMapFile)) {
        file_put_contents($containerMapFile, "<?php\n\$vendorDir = __DIR__ . '/';\n\nreturn array();\n");
    }

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
