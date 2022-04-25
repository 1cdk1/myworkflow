<?php

namespace MyWorkFlow\Flow\Run;

use MyWorkFlow\Constants\ClientReturn;
use MyWorkFlow\Constants\FlowCons;
use MyWorkFlow\Constants\TableName;
use think\db\ConnectionInterface;
use think\Exception;

/**
 *
 * @method static ok(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 * @method static back(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 * @method static sign(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 */
class Operation
{
    public static function __callStatic($name, $arguments)
    {
        list($db, $flowId, $userInfo, $opInfo) = $arguments;
        #校验操作类型
        if (!in_array($name, ['ok', 'back', 'sign'])) {
            return [
                'status' => ClientReturn::FAIL,
                'msg'    => '操作不存在',
            ];
        }
        #校验数据库配置
        if ($db instanceof ConnectionInterface) {
            try {
                #运行信息
                $runData = $db->name(TableName::RUN)->where('flow_id', $flowId)->find();
                if (empty($runData)) {
                    throw new Exception('未找到该运行中的流水线');
                }
                #当前步骤
                $nowProcessArr = $db->name(TableName::PROCESS)
                    ->whereIn('process_id', explode(',', $runData['now_process_ids']))
                    ->column('*','process_id');
                if (empty($nowProcessArr)) {
                    throw new Exception('当前流水线存在错误,请重新创建');
                }
                #判断角色是否可进行操作
                $processRole = array_column($nowProcessArr, 'role_ids');
                $allowRoleId = implode(',', $processRole);
                if (!in_array($userInfo['role_id'], explode(',', $allowRoleId))) {
                    throw new Exception('当前角色没有权限操作');
                }
                #todo 多步骤需判断为哪一步骤
                dump($processRole);exit;

                $nowProcess = $nowProcessArr[0];

                switch ($name) {
                    case 'ok':#通过
                        $db->startTrans();
                        #把当前运行步骤设为完成
                        $saveProcess = $db->name(TableName::RUN_PROCESS)->where([
                            'process_id' => $nowProcess['process_id'],
                            'run_id'     => $runData['run_id'],
                            'flow_id'    => $flowId,
                            'status'     => FlowCons::PROCESSING,
                        ])->update([
                            'status'           => FlowCons::FINISH,
                            'handle_time'      => time(),
                            'approval_opinion' => $opInfo['approval_opinion']
                        ]);
                        #更新运行日志
                        $logRes = $db->name(TableName::RUN_LOG)->insert([
                            'flow_id'    => $flowId,
                            'process_id' => $nowProcess['process_id'],
                            'run_id'     => $runData['run_id'],
                            'user_id'    => $userInfo['user_id'],
                            'content'    => '用户ID:' . $userInfo['user_id'] . '审批通过了' . $nowProcess['process_name'] . '步骤'
                        ]);
                        #当前步骤是否为结束
                        if ($nowProcess['process_type'] != FlowCons::END_PROCESS) {
                            #更新下一步骤
                            $nextProcess = $db->name(TableName::PROCESS)
                                ->whereIn('process_id', explode(',', $nowProcess['next_process_ids']))
                                ->select()
                                ->toArray();
                            if (empty($nextProcess)) {
                                throw new Exception('工作流存在异常，未找到下一步骤');
                            }
                            #组装数据
                            $nextProcessRunData = [];
                            array_map(function ($value) use (&$nextProcessRunData, $runData, $flowId) {
                                $nextProcessRunData[] = [
                                    'run_id'       => $runData['run_id'],
                                    'process_id'   => $value['process_id'],
                                    'flow_id'      => $flowId,
                                    'status'       => FlowCons::RUNNING_STATUS,
                                    'receive_time' => time(),
                                    'create_time'  => time(),
                                ];
                            }, $nextProcess);
                            $insertRunProcess = $db->name(TableName::RUN_PROCESS)->insertAll($nextProcessRunData);
                            #获取当前还在运行的步骤
                            $nowProcessIds = $db->name(TableName::RUN_PROCESS)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                    'status'  => FlowCons::PROCESSING
                                ])
                                ->column('process_id');

                            #更新运行表
                            $updateRun = $db->name(TableName::RUN)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                ])
                                ->update([
                                    'now_process_ids' => implode(',', $nowProcessIds),
                                    'update_time'     => time()
                                ]);
                        } else {
                            $insertRunProcess = true;
                            #变更运行表为已结束
                            $updateRun = $db->name(TableName::RUN)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                ])
                                ->update([
                                    'status'          => FlowCons::END_STATUS,
                                    'now_process_ids' => '',
                                    'end_time'        => time(),
                                    'update_time'     => time(),
                                ]);
                        }
                        if ($saveProcess && $logRes && $insertRunProcess && $updateRun) {
                            $db->commit();
                            return [
                                'status' => ClientReturn::SUCCESS,
                                'msg'    => '操作成功',
                            ];
                        } else {
                            throw new Exception('操作保存失败');
                        }
                        break;
                    case 'back':#驳回
                        #把当前步骤设为已驳回

                        #如果有回退ID则回退到对应步骤
                        break;
                }

            } catch (Exception $e) {
                $db->rollback();
                return [
                    'status' => ClientReturn::FAIL,
                    'msg'    => $e->getMessage(),
                ];
            }
        } else {
            return [
                'status' => ClientReturn::FAIL,
                'msg'    => '数据库配置异常',
            ];
        }
    }

}