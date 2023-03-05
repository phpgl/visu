<?php 

namespace VISU\OS;

/**
 * This VISU Logger class is used to log messages directly to STDOUT.
 * If you need fancy log redirections, transformations, etc. you should use a PSR-3 compatible logger or monolog.
 * This logger relies on global state and is here to have some control over the STDOUT output.
 */
class Logger
{
    /**
     * A rolling array of log messages, this allows us to dump the log messages on request
     * 
     * @var array<string>
     */
    public static array $messages = [];

    /**
     * The maximum number of messages to keep in the log
     */
    public static int $maxMessages = 2048;

    /**
     * Is the "INFO" log level enabled?
     */
    public static bool $levelInfoEnabled = true;

    /**
     * Is the "WARN" log level enabled?
     */
    public static bool $levelWarnEnabled = true;

    /**
     * Is the "ERROR" log level enabled?
     */
    public static bool $levelErrorEnabled = true;

    /**
     * Logs a message with the INFO log level
     */
    public static function info(string $message) : void
    {
        if (!self::$levelInfoEnabled) {
            return;
        }

        self::$messages[] = $message;
        if (count(self::$messages) > self::$maxMessages) {
            array_shift(self::$messages);
        }

        echo '[info]: ' . $message . PHP_EOL;
    }

    /**
     * Logs a message with the WARNING log level
     */
    public static function warn(string $message) : void
    {
        if (!self::$levelWarnEnabled) {
            return;
        }

        self::$messages[] = $message;
        if (count(self::$messages) > self::$maxMessages) {
            array_shift(self::$messages);
        }

        echo "\033[33m[warn]: " . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Logs a message with the ERROR log level
     */
    public static function error(string $message) : void
    {
        if (!self::$levelErrorEnabled) {
            return;
        }

        self::$messages[] = $message;
        if (count(self::$messages) > self::$maxMessages) {
            array_shift(self::$messages);
        }

        echo "\033[31m[error]: " . $message . "\033[0m" . PHP_EOL;
    }
}