<?php

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/heepay_jd.php';

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
    $modules[$i]['desc']    = 'heepay_jd_desc';

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
class heepay_jd
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

        $signs = array(
            'version' => 1,
            'agent_id' => intval($payment['agent_id']),
            'agent_bill_id' => $order['order_sn'],
            'agent_bill_time' => date('YmdHis', $order['add_time']),
            'pay_type' => 33,
            'pay_amt' => $order['order_amount'],
            'notify_url' => 'http://www.baidu.com',
            'return_url' => 'http://www.souhu.com',
            'user_ip' => implode('_', explode('.' , $_SERVER["REMOTE_ADDR"]))
        );
        $parameter = array(
            'goods_name' => 'null1',
            'goods_note' => 'null2',
            'goods_num' => '10',
            'remark' => 'null4',
        );

        $param = '';
        $sign  = '';

        foreach ($signs AS $key => $val)
        {
            $param .= "$key=$val&";
            $sign  .= "$key=$val&";
        }
        foreach ($parameter AS $key => $val)
        {
            $param .= "$key=$val&";
        }

        $param = strtolower(iconv( "UTF-8", "gb2312//IGNORE" , substr($param, 0, -1)));
        $sign  .= 'key='.$payment['key'];
        
        $url = 'https://pay.heepay.com/Payment/Index.aspx?'.$param.'&sign='.strtolower(md5(iconv( "UTF-8", "gb2312//IGNORE" , $sign)));
        $button = '<div style="text-align:center"><input type="button" onclick="window.open(\''.$url.'\')" value="' .$GLOBALS['_LANG']['pay_button']. '" /></div>';

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