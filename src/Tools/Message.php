<?php

namespace Dynamic\BlockMigration\Tools;

/**
 * Class Message
 * @package Dynamic\BlockMigration\Tools
 */
class Message
{
    /**
     * @param string $message
     */
    public static function terminal($message = '')
    {
        echo "{$message}\n";
    }

    /**
     * @param string $message
     */
    public static function browser($message = '')
    {
        echo "{$message}";
    }
}