<?php

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/heepay.php';

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
    $modules[$i]['desc']    = 'heepay_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'ST_YUAN';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.heepay.com';

    /* 版本号 */
    $modules[$i]['version'] = '1';

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
class heepay
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

        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        // $sign = array(
        //     'agent_bill_id' => $order['order_sn'],
        //     'agent_bill_time' => date('YmdHis', $order['add_time']),
        //     'agent_id' => intval($payment['agent_id']),
        //     'auth_card_type' => 0,
        //     'custom_page' => 1,
        //     'device_id' => '',
        //     'device_type' => 1,
        //     'ext_param1' => substr($order['postscript'], 0, 50),
        //     'ext_param2' => substr($order['postscript'], 50, 50),
        //     'goods_name' => '',
        //     'goods_note' => '',
        //     'goods_num' => 0,
        //     'hy_auth_uid' => '',
        //     'key' => '111',
        //     'mobile' => $order['tel'],
        //     'notify_url' => 'www.baidu.com',
        //     'pay_amt' => $order['goods_amount'],
        //     'return_url' => 'www.baidu.com',
        //     'timestamp' => $msectime,
        //     'user_identity' => $order['user_id'],
        //     'user_ip' => implode('_', explode('.' , $_SERVER["REMOTE_ADDR"])),
        //     'version' => 1
        // );

        $parameter = array(
            'agent_bill_id' => $order['order_sn'],
            'agent_bill_time' => date('YmdHis', $order['add_time']),
            'auth_card_type' => 0,
            'device_id' => '',
            'device_type' => 1,
            'ext_param1' => substr($order['postscript'], 0, 50),
            'ext_param2' => substr($order['postscript'], 50, 50),
            'goods_name' => '',
            'goods_note' => '',
            'goods_num' => 0,
            'hy_auth_uid' => '',
            'notify_url' => 'www.baidu.com',
            'return_url' => 'www.baidu.com',
            'timestamp' => $msectime,
            'user_identity' => $order['user_id'],
            'user_ip' => implode('_', explode('.' , $_SERVER["REMOTE_ADDR"])),
            'version' => 1,
            'display' => 0,
            'bank_card_no' => '',
            'bank_user' => '',
            'cert_no' => '',
            'mobile' => '',
            'bank_id' => 0
        );

        ksort($parameter);
        reset($parameter);

        $data = '';
        $sign  = '';

        //intval($payment['agent_id']),
        foreach ($parameter AS $key => $val)
        {
            $data .= "$key=" .urlencode($val). "&";
            $sign  .= "$key=$val&";
        }

        $param = substr($param, 0, -1);
        $sign  = substr($sign, 0, -1).$payment['key'];
        
        $agentId = $payment['agent_id'];

        include(ROOT_PATH . 'includes/cls_aes.php');

        $aes = new aes();

        $button = '<div style="text-align:center"><input type="button" onclick="window.open(\'https://mapi.alipay.com/gateway.do?'.$param. '&sign='.md5($sign).'&sign_type=MD5\')" value="' .$GLOBALS['_LANG']['pay_button']. '" /></div>';

        return $button;
    }

    /**
     * 响应操作
     */
    function respond()
    {
        if (!empty($_POST))
        {
            foreach($_POST as $key => $data)
            {
                $_GET[$key] = $data;
            }
        }
        $payment  = get_payment($_GET['code']);
        $seller_email = rawurldecode($_GET['seller_email']);
        $order_sn = str_replace($_GET['subject'], '', $_GET['out_trade_no']);
        $order_sn = trim(addslashes($order_sn));

        /* 检查数字签名是否正确 */
        ksort($_GET);
        reset($_GET);

        $sign = '';
        foreach ($_GET AS $key=>$val)
        {
            if ($key != 'sign' && $key != 'sign_type' && $key != 'code')
            {
                $sign .= "$key=$val&";
            }
        }

        $sign = substr($sign, 0, -1) . $payment['alipay_key'];
        //$sign = substr($sign, 0, -1) . ALIPAY_AUTH;
        if (md5($sign) != $_GET['sign'])
        {
            return false;
        }

        /* 检查支付的金额是否相符 */
        if (!check_money($order_sn, $_GET['total_fee']))
        {
            return false;
        }

        if ($_GET['trade_status'] == 'WAIT_SELLER_SEND_GOODS')
        {
            /* 改变订单状态 */
            order_paid($order_sn, 2);

            return true;
        }
        elseif ($_GET['trade_status'] == 'TRADE_FINISHED')
        {
            /* 改变订单状态 */
            order_paid($order_sn);

            return true;
        }
        elseif ($_GET['trade_status'] == 'TRADE_SUCCESS')
        {
            /* 改变订单状态 */
            order_paid($order_sn, 2);

            return true;
        }
        else
        {
            return false;
        }
    }
}

?>