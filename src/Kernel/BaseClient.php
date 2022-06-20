<?php
/**
 *  the base client
 * 基类client
 */

namespace MyWorkFlow\Kernel;

use MyWorkFlow\Constants\ClientReturn;
use MyWorkFlow\Constants\TableName;
use think\db\connector\Mysql;
use think\Exception;
use think\facade\Db;

class BaseClient
{
    /**
     * @var \MyWorkFlow\Kernel\BaseContainer
     */
    protected $app;

    /**
     * @var int
     */
    protected $nowTime;

    /**
     * @var \think\db\Query
     */
    protected $db;

    /**
     * 测试数据1
     *
     * @var array
     */
    protected $demoTemplate1
        = [
            'template_id'   => 1,     #模板ID
            'template_name' => '安责险审批流程',     #模板名
            'template_desc' => '用于安责险审批流程',   #模板描述
            #工作流程
            'flow_process'  => [
                [
                    'process_id'       => 1,                 #步骤ID
                    'process_name'     => '待指派专家',        #步骤名
                    'process_desc'     => '服务机构主管指派给专家',        #步骤描述
                    'process_type'     => 0,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 2,                 #下一步骤IDS
                    'role_ids'         => '1,4',                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签 
                    'status_id'        => 1,                 #对应状态ID
                ],
                [
                    'process_id'       => 2,                 #步骤ID
                    'process_name'     => '专家确认',        #步骤名
                    'process_desc'     => '专家确认服务',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 3,                 #下一步骤IDS
                    'role_ids'         => '1,6',                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签 
                    'status_id'        => 2,                 #对应状态ID
                ],
                [
                    'process_id'       => 3,                 #步骤ID
                    'process_name'     => '专家确认情况',        #步骤名
                    'process_desc'     => '专家确认是否需要整改',        #步骤描述
                    'process_type'     => 3,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '{"no_need_rectify":true}',
                    'jump_process_id'  => 7,
                    'next_process_ids' => 4,                 #下一步骤IDS
                    'role_ids'         => '1,6',                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签 
                    'status_id'        => 3,                 #对应状态ID
                ],
                [
                    'process_id'       => 4,                 #步骤ID
                    'process_name'     => '企业整改',        #步骤名
                    'process_desc'     => '企业整改专家反馈的情况',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 5,                 #下一步骤IDS
                    'role_ids'         => '1,2,6',                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签
                    'status_id'        => 4,                 #对应状态ID
                ],
                [
                    'process_id'       => 5,                 #步骤ID
                    'process_name'     => '专家审核',        #步骤名
                    'process_desc'     => '专家审核企业整改情况',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 6,                 #下一步骤IDS
                    'role_ids'         => '1,6',                 #审核角色IDS
                    'can_back'         => 1,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签
                    'status_id'        => 5,                 #对应状态ID
                ],
                [
                    'process_id'       => 6,                 #步骤ID
                    'process_name'     => '服务端审核',        #步骤名
                    'process_desc'     => '服务端审核企业整改情况',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 7,                 #下一步骤IDS
                    'role_ids'         => '1,4',                 #审核角色IDS
                    'can_back'         => 1,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签
                    'status_id'        => 5,                 #对应状态ID
                ],
                [
                    'process_id'       => 7,                 #步骤ID
                    'process_name'     => '结束',        #步骤名
                    'process_desc'     => '服务完成',        #步骤描述
                    'process_type'     => 2,                 #步骤类型  0为开始 1为步骤 2为结束 3为网关
                    'condition'        => '',
                    'jump_process_id'  => 0,
                    'next_process_ids' => 0,                 #下一步骤IDS
                    'role_ids'         => '1,4',                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0或签 1会签 
                    'status_id'        => 7,                 #对应状态ID
                ],
            ]
        ];

    protected $demoRole
        = [
            [
                'role_id'   => 1,
                'role_name' => '平台',
            ],
            [
                'role_id'   => 2,
                'role_name' => '企业',
            ],
            [
                'role_id'   => 3,
                'role_name' => '保险机构',
            ],
            [
                'role_id'   => 4,
                'role_name' => '服务',
            ],
            [
                'role_id'   => 5,
                'role_name' => '政府',
            ], [
                'role_id'   => 6,
                'role_name' => '专家',
            ],
        ];

    protected $demoStatus
        = [
            [
                'status_id'   => 1,
                'status_name' => '待受理',
            ],
            [
                'status_id'   => 2,
                'status_name' => '待服务',
            ],
            [
                'status_id'   => 3,
                'status_name' => '服务中',
            ],
            [
                'status_id'   => 4,
                'status_name' => '整改中',
            ],
            [
                'status_id'   => 5,
                'status_name' => '待专家审核',
            ],
            [
                'status_id'   => 6,
                'status_name' => '待服务端审核',
            ],
            [
                'status_id'   => 7,
                'status_name' => '服务完成',
            ],
        ];

    /**
     * Constructor
     * 构造器
     *
     * @param \MyWorkFlow\Kernel\BaseContainer $app
     */
    public function __construct(BaseContainer $app)
    {
        $this->app = $app;
        $this->nowTime = time();
        //数据库
        if ($app->offsetExists('db') && $app->offsetGet('db') instanceof Mysql) {
            $this->db = $app->offsetGet('db');
        } else {
            $this->db = Db::connect();
        }

        #验证token，如果不同重新获取并同步远端的基本数据
        if (!$app->offsetExists('token') || $app->offsetGet('token') != 'test') {
            //拉取远端状态信息
            $statusInfo = $this->demoStatus;
            //更新状态表
            $statusInsert = array_map(function ($v) {
                return [
                    'status_id'   => $v['status_id'],
                    'status_name' => $v['status_name'],
                    'create_time' => $this->nowTime,
                ];
            }, $statusInfo);
            $this->db->name(TableName::STATUS)->where('status_id', '>', 0)->delete();
            $this->db->name(TableName::STATUS)->insertAll($statusInsert);
            //更新角色表
            $roleInfo = $this->demoRole;
            $roleInsert = array_map(function ($v) {
                return [
                    'role_id'     => $v['role_id'],
                    'role_name'   => $v['role_name'],
                    'create_time' => $this->nowTime,
                ];
            }, $roleInfo);
            $this->db->name(TableName::ROLE)->where('role_id', '>', 0)->delete();
            $this->db->name(TableName::ROLE)->insertAll($roleInsert);
        }
    }

    /**
     * 统一返回方式
     *
     * @param $status
     * @param $msg
     * @param $data
     * @param $code
     *
     * @return array|false
     */
    public function _return($status = ClientReturn::SUCCESS, $msg = '', $data = [], $code = ClientReturn::NORMAL_CODE)
    {
        if (!in_array($status, [ClientReturn::FAIL, ClientReturn::SUCCESS])) {
            return false;
        }
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
            'code'   => $code
        ];
    }

    /**
     * 执行成功
     *
     * @param $data
     * @param $msg
     *
     * @return array|false
     */
    public function success($data, $msg = '')
    {
        return $this->_return(ClientReturn::SUCCESS, $msg, $data);
    }

    /**
     * 失败
     *
     * @param $msg
     * @param $code
     * @param $data
     *
     * @return array|false
     */
    public function fail($msg, $code = ClientReturn::NORMAL_ERROR_CODE, $data = [])
    {
        return $this->_return(ClientReturn::FAIL, $msg, $data, $code);
    }

    /**
     * 如果条件成立则抛出异常，调用时需捕获异常
     *
     * @param $condition
     * @param $msg
     *
     * @return void
     * @throws \think\Exception
     */
    public function assert($condition, $msg = '操作失败')
    {
        if ($condition) {
            throw new Exception($msg);
        }
    }
}