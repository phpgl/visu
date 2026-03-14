<?php

namespace VISU\Setup;

/**
 * Composer hook for automatic project setup.
 *
 * Triggered on `post-install-cmd` and `post-update-cmd`. Detects whether VISU
 * is running as a dependency (not standalone) and runs the interactive setup
 * only when something is missing. Existing files are never overwritten.
 *
 * Note: Composer\Script\Event is only available at Composer runtime, so we
 * avoid type-hinting it directly to keep PHPStan happy without requiring
 * composer/composer as a dev dependency.
 */
class ComposerSetupScript
{
    /**
     * @param mixed $event Composer\Script\Event instance
     */
    public static function postInstall($event): void
    {
        self::runSetup($event);
    }

    /**
     * @param mixed $event Composer\Script\Event instance
     */
    public static function postUpdate($event): void
    {
        self::runSetup($event);
    }

    /**
     * @param mixed $event Composer\Script\Event instance
     */
    private static function runSetup($event): void
    {
        /** @var string $vendorDir */
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = (string) realpath($vendorDir . '/..');

        // Detect if we are the root package (standalone / development mode).
        // In that case, don't scaffold — the developer is working on VISU itself.
        /** @var string $rootPackage */
        $rootPackage = $event->getComposer()->getPackage()->getName();
        if ($rootPackage === 'phpgl/visu') {
            return;
        }

        $io = $event->getIO();

        // Quick check: if all essential files exist, skip entirely (silent).
        if (self::isProjectReady($projectRoot)) {
            return;
        }

        $io->write('');
        $io->write('<info>VISU Engine</info> — Setting up project structure...');
        $io->write('');

        /** @var bool $interactive */
        $interactive = $io->isInteractive();

        $setup = new ProjectSetup(
            projectRoot: $projectRoot,
            interactive: $interactive,
            output: function (string $line) use ($io): void {
                $io->write($line);
            },
            confirm: function (string $question) use ($io): bool {
                /** @var bool $result */
                $result = $io->askConfirmation($question . ' [Y/n] ', true);
                return $result;
            },
        );

        $setup->run();
    }

    /**
     * Check whether all essential project files already exist.
     * If so, there's nothing to do and we can skip silently.
     */
    private static function isProjectReady(string $projectRoot): bool
    {
        $ds = DIRECTORY_SEPARATOR;
        $required = [
            $projectRoot . $ds . 'app.ctn',
            $projectRoot . $ds . 'var' . $ds . 'cache',
            $projectRoot . $ds . 'var' . $ds . 'store',
            $projectRoot . $ds . 'resources',
        ];

        foreach ($required as $path) {
            if (!file_exists($path)) {
                return false;
            }
        }

        return true;
    }
}
