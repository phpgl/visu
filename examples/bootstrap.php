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

/**
 * ---------------------------------------------------------------
 * Download Assets for the example
 * ---------------------------------------------------------------
 */
$exampleAssetsUrl = 'https://github.com/phpgl/visu-example-assets/archive/refs/heads/master.zip';
$exampleAssetsPath = VISU_PATH_RESOURCES . DS . 'assets';

if (!is_dir($exampleAssetsPath)) {
    if (!extension_loaded('zip')) {
        echo "Error: PHP Zip extension is required to download and extract example assets.\n";
        echo "Please install the PHP Zip extension and try again.\n";
        exit(1);
    }
    
    echo "Downloading example assets...\n";
    $zipFile = VISU_PATH_CACHE . DS . 'visu-example-assets.zip';
    file_put_contents($zipFile, fopen($exampleAssetsUrl, 'r'));
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo(VISU_PATH_RESOURCES);
        $zip->close();
        rename(VISU_PATH_RESOURCES . DS . 'visu-example-assets-master', $exampleAssetsPath);
        unlink($zipFile);
        echo "[ok] Example assets downloaded and extracted.\n";
    } else {
        echo "[error] Failed to download example assets.\n";
    }
} else {
    echo "[ok] Example assets already present.\n";
}

// forward the container
return $container;