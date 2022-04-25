<?php
/**
 * base container
 * 基类容器
 */

namespace MyWorkFlow\Kernel;

use Pimple\Container;

class BaseContainer extends Container
{
    /**
     * @var array
     */
    protected $providers = [];

    /**
     * Constructor
     * 构造器
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->registerProviders($this->getProviders());
    }

    /**
     * Return all providers.
     * 返回所有提供方法
     *
     * @return mixed
     */
    public function getProviders()
    {
        return array_merge([], $this->providers);
    }

    /**
     * register providers.
     * 注册提供方法
     *
     * @param array $providers
     *
     * @return void
     */
    public function registerProviders(array $providers)
    {
        foreach ($providers as $provider) {
            parent::register(new $provider());
        }
    }

    /**
     * Magic get access.
     * 魔术get方法
     *
     * @param string $id
     *
     * @return mixed
     */
    public function __get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Magic set access.
     * 魔术set方法
     *
     * @param $id
     * @param $value
     *
     * @return void
     */
    public function __set($id, $value)
    {
        $this->offsetSet($id, $value);
    }
}