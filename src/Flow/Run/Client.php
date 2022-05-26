<?php

namespace MyWorkFlow\Flow\Run;

use MyWorkFlow\Constants\ClientReturn;
use MyWorkFlow\Constants\FlowCons;
use MyWorkFlow\Kernel\BaseClient;
use MyWorkFlow\Kernel\BaseContainer;
use MyWorkFlow\Constants\TableName;
use think\Exception;

class Client extends BaseClient
{
    public function __construct(BaseContainer $app)
    {
        parent::__construct($app);
        //必须要有数据库对象传入
        if (empty($this->db)) {
            return false;
        }
    }

    /**
     * 创建本地工作流
     *
     * @param int   $templateId 工作流模板ID
     * @param array $params     创建参数
     *
     * @return array|false
     */
    public function createFlow(int $templateId, array $params)
    {
        //拉取远端模板ID
        $template = $this->demoTemplate1;
        //组装数据
        $this->db->startTrans();
        try {
            //插入本地工作流表
            $flowData = [
                'template_id'   => $template['template_id'],
                'template_name' => $template['template_name'],
                'template_desc' => $template['template_desc'],
                'flow_name'     => $params['flow_name'],
                'flow_desc'     => $params['flow_desc'],
                'create_time'   => $this->nowTime,
                'update_time'   => $this->nowTime
            ];
            $flowId = $this->db->name(TableName::FLOW)->insertGetId($flowData);

            //插入本地工作流流程
            #拿出开始,结束及普通节点
            $processData = [];
            array_map(function ($value) use (&$processData) {
                if ($value['process_type'] == FlowCons::START_PROCESS) {
                    $processData['start'] = $value + ['level' => 0, 'pid' => 0];
                } elseif ($value['process_type'] == FlowCons::END_PROCESS) {
                    $processData['end'] = $value + ['level' => 0, 'pid' => 0];
                } else {
                    $processData['process'][$value['process_id']] = $value;
                }
            }, $template['flow_process']);
            #递归整理所有普通节点，按顺序排列
            $tmpProcess = $this->_recursionProcess(
                $processData['process'], $processData['start']['next_process_ids'], $processData['start']['process_id']
            );
            #进行倒序处理，并添加回结束及开始节点。用于插入数据库
            $middleProcess = array_values(array_column(array_reverse($tmpProcess), null, 'process_id'));
            $finalProcess = array_merge([
                $processData['end']
            ], $middleProcess);
            $finalProcess[] = $processData['start'];

            #插入数据
            $localNextProcessIds = [];
            array_map(function ($value) use ($flowId, &$localNextProcessIds) {
                #计算出下一步骤的ID
                #判断是否有多个下一步
                if (stripos($value['next_process_ids'], ',') === false) {
                    #没有只需
                    $nextProcessId = empty($localNextProcessIds[$value['next_process_ids']]) ? 0
                        : $localNextProcessIds[$value['next_process_ids']];
                } else {
                    #存在多个下一步
                    $ids = [];
                    array_map(function ($id) use (&$ids, $localNextProcessIds) {
                        $ids[] = empty($localNextProcessIds[$id]) ? 0 : $localNextProcessIds[$id];;
                    }, explode(',', $value['next_process_ids']));
                    $nextProcessId = implode(',', $ids);
                }
                #组装数据
                $processData = [
                    'flow_id'          => $flowId,
                    'process_name'     => $value['process_name'],
                    'process_desc'     => $value['process_desc'],
                    'process_type'     => $value['process_type'],
                    'next_process_ids' => $nextProcessId,
                    'level'            => $value['level'],
                    'branch_pid'       => $value['pid'],
                    'role_ids'         => $value['role_ids'],
                    'status_id'        => $value['status_id'],
                    'can_back'         => $value['can_back'],
                    'sign_type'        => $value['sign_type'],
                    'condition'        => $value['condition'],
                    'jump_process_id'  => $value['jump_process_id'],
                    'create_time'      => $this->nowTime
                ];
                $nowId = $this->db->name(TableName::PROCESS)->insertGetId($processData);
                $this->assert((!$nowId), '添加步骤失败');
                $localNextProcessIds[$value['process_id']] = $nowId;
            }, $finalProcess);

            #更新跳转网关节点的跳转ID
            $gatewayProcess = $this->db->name(TableName::PROCESS)
                ->where([
                    'flow_id'      => $flowId,
                    'process_type' => FlowCons::GATEWAY_PROCESS
                ])
                ->select()
                ->toArray();
            array_map(function ($v) use ($localNextProcessIds) {
                $this->db->name(TableName::PROCESS)
                    ->where('process_id', $v['process_id'])
                    ->update([
                        'jump_process_id' => $localNextProcessIds[$v['jump_process_id']]
                    ]);
            }, $gatewayProcess);

            $this->assert((!$flowId), '创建失败');

            $this->db->commit();
            return $this->success(['flow_id' => $flowId]);
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->fail($e->getMessage(), ClientReturn::DATABASE_ERROR_CODE);
        }
    }

    /**
     * 发起工作流
     *
     * @param $flowId
     * @param $userId
     *
     * @return array|false
     */
    public function startFlow($flowId, $userId)
    {
        //开始工作流操作
        $this->db->startTrans();
        try {
            //找出要运行的工作流
            $flowData = $this->db->name(TableName::FLOW)->find($flowId);
            $this->assert(empty($flowData), '未找到对应工作流');
            #找出是否已经开启过
            $alreadyRunId = $this->db->name(TableName::RUN)->where([
                'flow_id' => $flowId
            ])->value('run_id');
            $this->assert($alreadyRunId, '该工作流已开启');

            //找出第一个步骤
            $firstProcess = $this->db->name(TableName::PROCESS)
                ->where([
                    'process_type' => 0,
                    'flow_id'      => $flowId
                ])->find();
            $this->assert(empty($firstProcess), '工作流存在错误，缺失第一步流程！');


            //发起工作流
            $runId = $this->db->name(TableName::RUN)->insertGetId([
                'user_id'         => $userId,
                'flow_id'         => $flowId,
                'now_process_ids' => $firstProcess['process_id'],
                'status'          => FlowCons::RUNNING_STATUS,
                'start_time'      => $this->nowTime,
                'create_time'     => $this->nowTime,
            ]);
            $this->assert(empty($runId), '发起工作流失败');

            //添加流程步骤
            $runProcessId = $this->db->name(TableName::RUN_PROCESS)->insertGetId([
                'run_id'       => $runId,
                'process_id'   => $firstProcess['process_id'],
                'flow_id'      => $flowId,
                'status'       => FlowCons::RUNNING_STATUS,
                'receive_time' => $this->nowTime,
                'create_time'  => $this->nowTime,
            ]);

            //添加日志
            $log = $this->db->name(TableName::RUN_LOG)->insert([
                'flow_id'     => $flowId,
                'process_id'  => $firstProcess['process_id'],
                'run_id'      => $runId,
                'user_id'     => $userId,
                'create_time' => $this->nowTime,
                'content'     => '用户ID:' . $userId . '发起了新的名为' . $flowData['flow_name'] . '的工作流',
            ]);

            $this->assert(!$log || !$runProcessId || !$runId, '发起工作流失败');
            $this->db->commit();
            return $this->success(['run_id' => $runId]);
        } catch (Exception $exception) {
            $this->db->rollback();
            return $this->fail($exception->getMessage());
        }
    }

    /**
     * 运行流水线
     *
     * @param string $operation 操作 ok通过 back驳回 sign会签
     *
     * @param int    $flowId
     * @param array  $userInfo
     * @param array  $opInfo    操作信息
     *
     * @return array|false
     */
    public function runFlow(string $operation, int $flowId, array $userInfo, array $opInfo)
    {
        $res = Operation::$operation($this->db, $flowId, $userInfo, $opInfo);
        return $res['status'] ? $this->success($res) : $this->fail($res['msg']);
    }

    /**
     * 获取当前步骤
     *
     * @param $flowId
     *
     * @return array|false
     */
    public function getNowProcess($flowId)
    {
        try {
            $runData = $this->db->name(TableName::RUN)->where('flow_id', $flowId)->find();
            if (empty($runData)) {
                throw new Exception('未找到该运行中的流水线');
            }
            #当前步骤
            $nowProcessArr = $this->db->name(TableName::PROCESS)->alias('p')
                ->leftJoin(TableName::STATUS, TableName::STATUS . '.status_id=p.status_id')
                ->whereIn('p.process_id', explode(',', $runData['now_process_ids']))
                ->field('p.*,' . TableName::STATUS . '.status_name')
                ->select()
                ->toArray();
            if (empty($nowProcessArr)) {
                throw new Exception('当前流水线存在错误,请重新创建');
            }
            return $this->success($nowProcessArr);
        } catch (Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取当前角色在某一流水线可进行的操作
     *
     * @param $flowId
     * @param $roleId
     *
     * @return array|false
     */
    public function getCanOperate($flowId, $roleId)
    {
        try {
            $runData = $this->db->name(TableName::RUN)
                ->where([
                    'flow_id' => $flowId,
                    'status'  => FlowCons::RUNNING_STATUS
                ])
                ->find();
            if (empty($runData)) {
                throw new Exception('未找到该流水线,检查是否为运行状态');
            }
            #当前步骤
            $nowProcessArr = $this->db->name(TableName::PROCESS)
                ->whereIn('process_id', explode(',', $runData['now_process_ids']))
                ->select()
                ->toArray();
            if (empty($nowProcessArr)) {
                throw new Exception('当前流水线存在错误,请重新创建');
            }
            $nowProcess = null;
            array_map(function ($value) use ($roleId, &$nowProcess) {
                $roleIds = explode(',', $value['role_ids']);
                if (in_array($roleId, $roleIds) && $nowProcess === null) {
                    $nowProcess = $value;
                }
            }, $nowProcessArr);
            if ($nowProcess === null) {
                throw new Exception('当前角色没有权限操作');
            } else {
                $op = ['ok', 'sign'];
                if ($nowProcess['can_back']) {
                    $op[] = 'back';
                }
            }
            return $this->success($op);
        } catch (Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 获取角色在多个工作流中的操作权限
     *
     * @param $flowIds
     * @param $roleId
     *
     * @return array|false
     */
    public function getRoleCanOperate($flowIds, $roleId)
    {
        try {
            if (is_string($flowIds)) {
                $flowIds = explode(',', $flowIds);
            }
            $runData = $this->db->name(TableName::RUN)
                ->whereIn('flow_id', $flowIds)
                ->where([
                    'status' => FlowCons::RUNNING_STATUS
                ])
                ->column('now_process_ids', 'flow_id');
            $process = $this->db->name(TableName::PROCESS)
                ->whereIn('process_id', explode(',', implode(',', $runData)))
                ->select()
                ->toArray();
            $canProcess = null;
            array_map(function ($value) use ($roleId, &$canProcess) {
                $roleIds = explode(',', $value['role_ids']);
                if (in_array($roleId, $roleIds) && empty($canProcess[$value['flow_id']])) {
                    $canProcess[$value['flow_id']] = $value['can_back'] ? ['ok', 'sign', 'back'] : ['ok', 'sign'];
                } else {
                    $canProcess[$value['flow_id']] = [];
                }
            }, $process);
            return $this->success($canProcess);
        } catch (Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 按流程递归排序步骤
     *
     * @param $process
     * @param $processIds
     *
     * @return array
     */
    public function _recursionProcess($process, $processIds, $pid = 0, $level = 1)
    {
        $ids = explode(',', $processIds);
        $data = [];
        #说明分叉了，层级加一
        if (count($ids) > 1) {
            $level += 1;
        }
        foreach ($ids as $v) {
            if (isset($process[$v])) {
                $data[] = $process[$v] + ['level' => $level, 'pid' => $pid];
                if ($process[$v]['next_process_ids']) {
                    if (count(explode(',', $process[$v]['next_process_ids'])) > 1) {
                        $pid = $process[$v]['process_id'];
                    }
                    $tmp = $this->_recursionProcess($process, $process[$v]['next_process_ids'], $pid, $level);
                    if (!empty($tmp)) {
                        $data = array_merge($data, $tmp);
                    }
                }
            }
        }
        return $data;
    }
}