<?php

namespace MyWorkFlow\Constants;

class FlowCons
{
    //工作流执行状态
    const WAIT_STATUS = 0;
    const RUNNING_STATUS = 1;
    const END_STATUS = 2;

    //工作流步骤状态
    const WAIT_PROCESS = 0;
    const PROCESSING = 1;
    const AGREE = 2;
    const FINISH = 3;
    const ROLL_BACK = -1;

    #步骤类型
    const START_PROCESS = 0;
    const STEP_PROCESS = 1;
    const END_PROCESS = 2;
    const GATEWAY_PROCESS = 3;

    #签名模式
    const  SIGN_OR = 0; #或签
    const  SIGN_AND = 1;#会签
}