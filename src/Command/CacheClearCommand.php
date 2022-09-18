<?php 

namespace VISU\Command;

use VISU\Exception\ErrorException;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class CacheClearCommand extends Command
{
    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = 'Clears the applications cache. (solves 99% of all issues)';

    /**
     *. Execute this command 
     */
    public function execute()
    {
        $this->info('clearing cache...');

        $fileCount = 0;

        $directoryIterator = new RecursiveDirectoryIterator(VISU_PATH_CACHE, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) 
        {
            $fileCount++;

            $file->isDir() ?  rmdir($file) : unlink($file);

            if ($this->verbose) {
                $this->cli->out(' - [<red>removed</red>] ' . $file);
            }
        }

        $this->success("Cache has been cleared. $fileCount files deleted.");
    }
}
