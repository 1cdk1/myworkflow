<?php

namespace MyWorkFlow;

/**
 * class Factory
 *
 * @method static \MyWorkFlow\Config\Application            config(array $config)
 * @method static \MyWorkFlow\Flow\Application              flow(array $config)
 *
 */
class Factory
{
    /**
     * @param       $name
     * @param array $config
     *
     * @return mixed
     */
    public static function make($name, array $config)
    {
        $nameSpace = ucwords($name);
        $application = "\\MyWorkFlow\\{$nameSpace}\\Application";

        return new $application($config);
    }

    /**
     * Dynamically pass methods to the application.
     * 动态调用对应的应用
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return self::make($name, ...$arguments);
    }

}