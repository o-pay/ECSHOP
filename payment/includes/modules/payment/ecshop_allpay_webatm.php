<?php

if (!defined('IN_ECS')) {
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' . $GLOBALS['_CFG']['lang'] . '/payment/ecshop_allpay_webatm.php';

if (file_exists($payment_lang)) {
    global $_LANG;

    include_once($payment_lang);
}

/* 模塊的基本信息 */
if (isset($set_modules) && $set_modules == TRUE) {
    $i = isset($modules) ? count($modules) : 0;

    /* 代碼 */
    $modules[$i]['code'] = basename(__FILE__, '.php');

    /* 描述對應的語言項 */
    $modules[$i]['desc'] = 'ecshop_allpay_webatm_desc';

    /* 是否支持貨到付款 */
    $modules[$i]['is_cod'] = '0';

    /* 是否支持在線支付 */
    $modules[$i]['is_online'] = '1';

    /* 排序 */
    //$modules[$i]['pay_order']  = '1';

    /* 作者 */
    $modules[$i]['author'] = '歐付寶';

    /* 網址 */
    $modules[$i]['website'] = 'https://www.allpay.com.tw';

    /* 版本號 */
    $modules[$i]['version'] = 'V1.0.1124';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('name' => 'ecshop_allpay_webatm_test_mode', 'type' => 'select', 'value' => 'Yes'),
        array('name' => 'ecshop_allpay_webatm_account', 'type' => 'text', 'value' => '2000132'),
        array('name' => 'ecshop_allpay_webatm_iv', 'type' => 'text', 'value' => 'v77hoKGq4kWxNNIS'),
        array('name' => 'ecshop_allpay_webatm_key', 'type' => 'text', 'value' => '5294y06JbISpM5x9')
    );
    return;
}

include_once(ROOT_PATH . '/includes/modules/AllPay.Payment.Integration.php');

/**
 * 類
 */
class ecshop_allpay_webatm extends AllInOne {

    /**
     * 構造函數
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function __construct() {
        parent::__construct();
        $this->ecshop_allpay_webatm();
    }

    function ecshop_allpay_webatm() {
        
    }

    /**
     * 提交函數
     */
    function get_code($order, $payment) {
        $isTestMode = ($payment['ecshop_allpay_webatm_test_mode'] == 'Yes');

        $this->ServiceURL = ($isTestMode ? "https://payment-stage.allpay.com.tw/Cashier/AioCheckOut" : "https://payment.allpay.com.tw/Cashier/AioCheckOut");
        $this->HashKey = trim($payment['ecshop_allpay_webatm_key']);
        $this->HashIV = trim($payment['ecshop_allpay_webatm_iv']);
        $this->MerchantID = trim($payment['ecshop_allpay_webatm_account']);

        $szRetUrl = return_url(basename(__FILE__, '.php')) . "&log_id=" . $order['log_id'] . "&order_id=" . $order['order_id'];
        $szRetUrl = str_ireplace('/mobile/', '/', $szRetUrl);

        $this->Send['ReturnURL'] = $szRetUrl . '&background=1';
        $this->Send['ClientBackURL'] = $GLOBALS['ecs']->url();
        $this->Send['OrderResultURL'] = $szRetUrl;
        $this->Send['MerchantTradeNo'] = $order['order_sn'];
        $this->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
        $this->Send['TotalAmount'] = intval($order['order_amount']);
        $this->Send['TradeDesc'] = "allpay_module_ecshop_1.0.1021";
        $this->Send['ChoosePayment'] = PaymentMethod::WebATM;
        $this->Send['Remark'] = '';
        $this->Send['ChooseSubPayment'] = PaymentMethodItem::None;
        $this->Send['NeedExtraPaidInfo'] = ExtraPaymentInfo::Yes;
        
        array_push($this->Send['Items'], array('Name' => $GLOBALS['_LANG']['text_goods'], 'Price' => intval($order['order_amount']), 'Currency' => $GLOBALS['_LANG']['text_currency'], 'Quantity' => 1, 'URL' => ''));

        try {
            return $this->CheckOutString($GLOBALS['_LANG']['pay_button']);
        } catch (Exception $e) {
            return '<script language="text/javascript">alert("' . $e->getMessage() . '");</script>';
        }
    }

    /**
     * 處理函數
     */
    function respond() {
        $arPayment = get_payment('ecshop_allpay_webatm');
        $isTestMode = ($arPayment['ecshop_allpay_webatm_test_mode'] == 'Yes');

        $arFeedback = null;
        $arQueryFeedback = null;
        $szLogID = $_GET['log_id'];
		$szOrderID = $_GET['order_id'];

        $this->HashKey = trim($arPayment['ecshop_allpay_webatm_key']);
        $this->HashIV = trim($arPayment['ecshop_allpay_webatm_iv']);

        try {
            // 取得回傳的付款結果。
            $arFeedback = $this->CheckOutFeedback();

            if (sizeof($arFeedback) > 0) {
                // 查詢付款結果資料。
				$this->ServiceURL = ($isTestMode ? "https://payment-stage.allpay.com.tw/Cashier/QueryTradeInfo/V2" : "https://payment.allpay.com.tw/Cashier/QueryTradeInfo/V2");
                $this->MerchantID = trim($arPayment['ecshop_allpay_webatm_account']);
                $this->Query['MerchantTradeNo'] = $arFeedback['MerchantTradeNo'];

                $arQueryFeedback = $this->QueryTradeInfo();

                if (sizeof($arQueryFeedback) > 0) {
					$arOrder = order_info($szOrderID);
                    // 檢查支付金額與訂單是否相符。
					if (round($arOrder['order_amount']) == $arFeedback['TradeAmt'] && $arQueryFeedback['TradeAmt'] == $arFeedback['TradeAmt']) {
                        $szCheckAmount = '1';
                    }
                    // 確認付款結果。
                    if ($arFeedback['RtnCode'] == '1' && $szCheckAmount == '1' && $arQueryFeedback["TradeStatus"] == '1') {
                        $szNote = $GLOBALS['_LANG']['text_paid'] . date("Y-m-d H:i:s");

                        order_paid($szLogID, PS_PAYED, $szNote);

                        if ($_GET['background']){
                            echo '1|OK';
                            exit;
                        } else {
                            return true;
                        }
                    } else {
                        if ($_GET['background']){
                            echo (!$szCheckAmount ? '0|訂單金額不符。' : $arFeedback['RtnMsg']);
                            exit;
                        } else {
                            return false;
                        }
                    }
                } else {
                    throw new Exception('AllPay 查無訂單資料。');
                }
            }
        } catch (Exception $ex) { /* 例外處理 */
        }

        return false;
    }

}
