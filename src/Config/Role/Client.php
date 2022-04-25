<?php

namespace MyWorkFlow\Config\Role;

use MyWorkFlow\Kernel\BaseClient;

class Client extends BaseClient
{
    public function getList()
    {
        return json_encode($this->demoRole);
    }

    public function getOne($id)
    {
        return $id;
    }

}