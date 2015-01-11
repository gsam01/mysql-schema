<?php

require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/util_extra.php';
require_once __DIR__ . '/src/autoload.php';

Config::init();

class Config
{

    protected static $cfg;

    public static function init()
    {
        static::$cfg['db.dsn'] = 'mysql:host=127.0.0.1;port=3306;dbname=test;username=root;password=';
    }

    public static function get($strKey)
    {
        return static::$cfg[$strKey];
    }

    public static function set($strKey, $mixValue)
    {
        static::$cfg[$strKey] = $mixValue;
    }

}
