<?php

namespace Dynamic\BlockMigration\Tools;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class Message
 * @package Dynamic\BlockMigration\Tools
 */
class Message
{
    use Configurable;
    use Injectable;

    /**
     * @var bool
     */
    private static $write_messages = true;

    /**
     * @param string $message
     */
    public static function terminal($message = '')
    {
        if (static::singleton()->config()->get('write_message')) {
            echo "{$message}\n";
        }
    }

    /**
     * @param string $message
     */
    public static function browser($message = '')
    {
        if (static::singleton()->config()->get('write_message')) {
            echo "{$message}";
        }
    }
}