<?php

class Pay {

    ///获取异步支付信息
    function getMiniNotify() {
        $data["result"] = "fail";
        $data["data"] = null;

        require_once "lib_china_mini/log.php";
        $logHandler = new CLogFileHandler("logs/" . date('Y-m-d') . '.log');
        $log = Log::Init($logHandler, 15);

        Log::INFO("getMiniNotify 开始接收支付回调数据时间：" . date("Y-m-d H:i:s", time()));

        $dataXml = file_get_contents('php://input');
        Log::INFO("getMiniNotify 接收XML数据内容：" . $dataXml);
        $payData = json_decode(json_encode(simplexml_load_string($dataXml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($payData) {
            Log::INFO("getMiniNotify 接收XML转换JSON数据内容：" . json_encode($payData));
            $wxResult = $this->query_by_out_trade_no($payData["out_trade_no"]);
            if ($wxResult && $wxResult["trade_state"] == "SUCCESS" && $wxResult["return_code"] == "SUCCESS") {//验证成功 
                $data["data"] = $wxResult;
                $data["result"] = "success";
                Log::INFO("getMiniNotify 结束接收支付信息处理时间：" . json_encode($wxResult) . "  " . json_encode($payData) . "   " . date("Y-m-d H:i:s", time()));
            }
        }
        Log::INFO("getMiniNotify 结束接收支付回调数据时间：" . date("Y-m-d H:i:s", time()));
        return $data;
    }

    //发起微信小程序支付 直接调起微信客户端支付
    public function payWxMiniChina($data, $config) {
        $payInfo = array();
        require_once "lib_china_mini/log.php";
        require_once "lib_china_mini/WxPay.Api.php";
        require_once "lib_china_mini/WxPay.JsApiPay.php";

        //初始化日志
        $logHandler = new CLogFileHandler("logs/" . date('Y-m-d') . '.log');
        Log::Init($logHandler, 15);
        try {

            //①、获取用户openid
            $tools = new JsApiPay();
            $openId = $data["openid"];

            //②、统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody($data["body"]);
            $input->SetAttach($data["subject"]);
            $input->SetOut_trade_no($data["trade_id"]);
            $input->SetTotal_fee($data["pay_price"] * 100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetNotify_url("/pay/notify/callback_weixin_notify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openId);
            $order = WxPayApi::unifiedOrder($config, $input);
            $payInfo["openId"] = $openId;
            $payInfo["subject"] = $data["subject"];
            $payInfo["trade_id"] = $data["trade_id"];
            $payInfo["pay_price"] = $data["pay_price"];
            $payInfo["jsApiParameters"] = $tools->GetJsApiParameters($order);
        } catch (Exception $e) {
            Log::ERROR(json_encode($e));
        }
        return $payInfo;
    }

    ///微信通过微信支付订单号查询订单信息
    public function queryByTransactionId($transaction_id, $config) {
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);
        return WxPayApi::orderQuery($config, $input);
    }

    ///微信通过商户订单号查询订单信息
    public function query_by_out_trade_no($out_trade_no, $config) {
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayOrderQuery();
        $input->SetOut_trade_no($out_trade_no);
        return WxPayApi::orderQuery($config, $input);
    }

    ///通过商户订单号微信退款申请处理
    public function refund_apply_order($data, $config) {
        require_once "lib_china_mini/log.php";
        require_once "lib_china_mini/WxPay.Api.php";
        $price = $data["pay_price"] * 100;
        $input = new WxPayRefund();
        $input->SetTransaction_id($data["pay_no"]);
        $input->SetOut_trade_no($data["order_no"]);
        $input->SetOut_refund_no($data["refund_no"]);
        $input->SetTotal_fee($price);
        $input->SetRefund_fee($price);
        $input->SetOp_user_id($config->GetMerchantId());
        return WxPayApi::refund($config, $input);
    }

    ///通过商户订单号微信退款查询处理
    public function refund_query_order($data, $config) {
        require_once "lib_china_mini/log.php";
        require_once "lib_china_mini/WxPay.Api.php";
        $price = $data["pay_price"] * 100;
        $input = new WxPayRefund();
        $input->SetTransaction_id($data["pay_no"]);
        $input->SetOut_trade_no($data["order_no"]);
        $input->SetOut_refund_no($data["refund_no"]);
        $input->SetTotal_fee($price);
        $input->SetRefund_fee($price);
        $input->SetOp_user_id($config->GetMerchantId());
        return WxPayApi::refundQuery($config, $input);
    }

}
