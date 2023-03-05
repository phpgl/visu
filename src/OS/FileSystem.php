<?php 

namespace VISU\OS;

class FileSystem
{
    /**
     * Returns boolean if the given path is in a containing directory.
     * 
     * @param string $path The path to check.
     * @param string $directory The directory the path should be in.
     * @return bool
     */
    public static function isPathInDirectory(string $path, string $directory) : bool
    {
        $directory = realpath($directory);
        $path = realpath($path);

        if ($directory === false || $path === false) {
            return false;
        }

        return strpos($path, $directory) === 0;
    }
}