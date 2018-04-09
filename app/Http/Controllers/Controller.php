<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $startTime;

    function __construct(){
        $startTime = microtime(true);
        $debug = config("app.api_debug");

        if($debug == false){
            $sign = trim($_POST['sign']);
            unset($_POST['sign']);
            $sign = $this->checkSign($_POST, $sign);

            if($sign == false){
                $res = array(
                    "errNo" => '0001',
                    "errMsg" => '签名校验失败'
                );

                $this->_response($res);
            }
        }
        
    }
    /**
     * 统一出口 
     * Author JiaXu
     * Date 2018-04-04
     * Params [params]
     * @param  array  $res [description]
     */
    public function _response($res = array())
    {
        header('Content-type: application/json');
        echo json_encode($res);
        exit;
    }

    /**
     * 校验签名信息 
     * Author Raven
     * Date 2018-04-09
     * Params [params]
     * @param  array  $params [签名参数]
     * @param  string $sign   [签名字符串]
     */
    public function checkSign($params = array(), $sign = '')
    {
        $signStr = '';

        foreach ($params as $key => $value) {
            $signStr .= $key . $value;
        }

        if(abs(time() - $params['timestamp']) > 300){
            //如果客户端请求时间与服务器时间相差 5分钟 拒绝请求
            return false;
        }
        $secret = config("app.api_secret");

        $signStr .= $secret;

        $serSign = md5($signStr);

        $check = $serSign == $sign;

        return $check;
    }
}
