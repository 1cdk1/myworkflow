<?php

namespace MyWorkFlow\Config\Role;

use MyWorkFlow\Kernel\BaseClient;

class Client extends BaseClient
{
    public function getList()
    {
        return json_encode($this->demoRole);
    }

    public function getOne($id): array
    {
        $data = [];
        array_map(function ($v) use ($id, &$data) {
            if ($v['role_id'] == $id) {
                $data = $v;
            }
        }, $this->demoRole);
        return $data;
    }

}