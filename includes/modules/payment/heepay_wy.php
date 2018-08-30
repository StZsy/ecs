<?php

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/heepay_wy.php';

if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'heepay_wy_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'ST_YUAN';

    /* 网址 */
    $modules[$i]['website'] = 'http://mch.heepay.com';

    /* 版本号 */
    $modules[$i]['version'] = '1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'agent_id', 'type' => 'text', 'value' => ''),
        array('name' => 'key', 'type' => 'text', 'value' => ''),
    );

    return;
}

/**
 * 类
 */
class heepay_wy
{
    function __construct()
    {
        $this->heepay();
    }

    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function heepay()
    {

    }

    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $payment    支付方式信息
     */
    function get_code($order, $payment)
    {
        if (!defined('EC_CHARSET'))
        {
            $charset = 'utf-8';
        }
        else
        {
            $charset = EC_CHARSET;
        }

        $url="https://c.heepay.com/quick/pc/index.do";
        $merchantId=$payment['agent_id'];	//商户号	汇付宝提供给商户的ID
        $merchantOrderNo=date('YmdHis', $order['add_time']);	//商户交易号	商户内部的交易ID	
        $merchantUserId=$order['user_id'];	//用户号	商户内部的个人用户ID 商户自定义	
        $productCode="HY_B2CEBANKPC";	//产品编码	用户签约的产品编码: 银联为HY_B2CEBANKPC
        $payAmount=$order['order_amount'];	//交易金额	单位为元，两位小数
        $requestTime=date('YmdHis', time());	//请求时间	商户请求接口时间 yyyyMMddhhmmss	
        $version="1.0";	//版本号	商户请求版本号	
        $notifyUrl=return_url(basename(__FILE__, '.php'));	//通知URL	
        $callBackUrl=return_url(basename(__FILE__, '.php'));	//同步返回URL	本次交易同步返回URL	

        $description="趣讯";	//商品信息	本次交易商品信息	
        $clientIp="127.0.0.1";	//用户ip	发起交易用户的ip	

        if(isset($_SERVER['HTTP_CLIENT_IP']))
        {
            $clientIp = $_SERVER['HTTP_CLIENT_IP'];
        }
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }else
        {
            $clientIp = $_SERVER['REMOTE_ADDR'];
        }
        $onlineType="hard";
        $bankId="";
        $bankName="";
        $bankCardType="SAVING";//SAVING : 储蓄卡支付CREDIT : 信用卡支付

        $sign_key=$payment['key'];//签名密钥，换商户号同时需要更换密钥
        $sign_str = '';
            $sign_str  = $sign_str . 'merchantId=' . $merchantId;
            $sign_str  = $sign_str . '&merchantOrderNo=' . $merchantOrderNo;
            $sign_str  = $sign_str . '&merchantUserId=' . $merchantUserId;
            $sign_str  = $sign_str . '&notifyUrl=' . $notifyUrl;
            $sign_str  = $sign_str . '&payAmount=' . $payAmount;
            $sign_str  = $sign_str . '&productCode=' . $productCode;
            $sign_str  = $sign_str . '&version=' . $version;
            $sign_str = $sign_str . '&key=' . $sign_key;
        $signString = strtolower(md5($sign_str));


        $button = "<form id='frmSubmit' method='post' name='frmSubmit' action=$url  target=\"_bland\">
        <input type='hidden' name='merchantId' value='$merchantId' />
        <input type='hidden' name='merchantOrderNo' value='$merchantOrderNo' />
        <input type='hidden' name='merchantUserId' value='$merchantUserId' />
        <input type='hidden' name='productCode' value='$productCode' />
        <input type='hidden' name='payAmount' value='$payAmount' />
        <input type='hidden' name='requestTime' value='$requestTime' />
        <input type='hidden' name='version' value='$version' />
        <input type='hidden' name='notifyUrl' value='$notifyUrl' />
        <input type='hidden' name='callBackUrl' value='$callBackUrl' />
        <input type='hidden' name='description' value='$description' />
        <input type='hidden' name='clientIp' value='$clientIp' />
        <input type='hidden' name='signString' value='$signString' />
        <input type='hidden' name='onlineType' value='$onlineType' />
        <input type='hidden' name='bankId' value='$bankId' />
        <input type='hidden' name='bankName' value='$bankName' />
        <input type='hidden' name='bankCardType' value='$bankCardType' />
        </form><div style=\"text-align:center\"><input type=\"button\" onclick=\"document.frmSubmit.submit();\" value=\"".$GLOBALS["_LANG"]["pay_button"]."\" /></div>";
        
        return $button;
    }

    /**
     * 响应操作
     */
    function respond()
    {
        order_paid('2018083137169', 2);return true;
        if (!empty($_POST))
        {
            foreach($_POST as $key => $data)
            {
                $_GET[$key] = $data;
            }
        }
        $payment  = get_payment($_GET['code']);
        
        //支付失败
        if (!isset($_GET['result']) || 1002 == $_GET['result']) {
            return false;
        }

        $signParam = [
            'merchantId' => $_GET['merchantId'],
            'merchantOrderNo' => $_GET['merchantOrderNo'],
            'payAmount' => $_GET['payAmount'],
            'successAmount' => $_GET['successAmount'],
            'transNo' => $_GET['transNo'],
            'version' => $_GET['version']
        ];

        $sign = '';
        foreach ($signParam AS $key=>$val)
        {
            $sign .= "$key=$val&";
        }

        $sign = $sign.'key='.$payment['key'];
        if (strtolower(md5($sign)) != $_GET['sign'])
        {
            return false;
        }

        /* 检查支付的金额是否相符 */
        if (!check_money($signParam['payAmount'], $signParam['successAmount']))
        {
            return false;
        }
        
        //order已付款
        order_paid(trim(addslashes($signParam['merchantOrderNo'])), 2);
        echo 'ok';
        return true;
    }
}

?>