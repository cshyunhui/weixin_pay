<?php

class Pay {
    
    
    /*     * 获取异步支付信息
     * 
     */
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

    /*     * 发起微信小程序支付 返回客户端支付信息
     * @param $openId 支付用户openid
     * @param $subject 商品名称
     * @param $body 商品body信息
     * @param $order_no 商户订单号
     * @param $pay_price 支付金额(需乘以100再传)
     * @param $config 支付配置文件信息
     */
    public function payWxMiniChina($openId, $subject, $body, $order_no, $pay_price, $config) {
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

            //②、统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody($body);
            $input->SetAttach($subject);
            $input->SetOut_trade_no($order_no);
            $input->SetTotal_fee($pay_price);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetNotify_url("/pay/notify/callback_weixin_notify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openId);
            $order = WxPayApi::unifiedOrder($config, $input);
            $payInfo["openId"] = $openId;
            $payInfo["subject"] = $subject;
            $payInfo["order_no"] = $order_no;
            $payInfo["pay_price"] = $pay_price;
            $payInfo["jsApiParameters"] = $tools->GetJsApiParameters($order);
        } catch (Exception $e) {
            Log::ERROR(json_encode($e));
        }
        return $payInfo;
    }

    /*     * 微信通过微信支付订单号查询订单信息
     * @param $trade_id 支付流水号 
     * @param $config 支付配置文件信息
     */
    public function queryByTransactionId($trade_id, $config) {
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($trade_id);
        return WxPayApi::orderQuery($config, $input);
    }

    /*     * 微信通过商户订单号查询订单信息
     * @param $order_no 商户订单号 
     * @param $config 支付配置文件信息
     */
    public function query_by_out_trade_no($order_no, $config) {
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayOrderQuery();
        $input->SetOut_trade_no($order_no);
        return WxPayApi::orderQuery($config, $input);
    }

    /*     * 通过商户订单号微信退款申请处理 金额(需乘以100再传)
     * @param $trade_id 支付流水号
     * @param $order_no 商户订单号 
     * @param $refund_no 退款订单号
     * @param $total_price 此订单支付金额
     * @param $refund_price 退款金额 
     * @param $config 支付配置文件信息
     */
    public function refund_apply_order($trade_id, $order_no, $refund_no, $total_price, $refund_price, $config) {
        require_once "lib_china_mini/log.php";
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayRefund();
        $input->SetTransaction_id($trade_id);
        $input->SetOut_trade_no($order_no);
        $input->SetOut_refund_no($refund_no);
        $input->SetTotal_fee($total_price);
        $input->SetRefund_fee($refund_price);
        $input->SetOp_user_id($config->GetMerchantId());
        return WxPayApi::refund($config, $input);
    }

    /*     * 通过商户订单号微信退款查询处理 金额(需乘以100再传)
     * @param $trade_id 支付流水号
     * @param $order_no 商户订单号 
     * @param $refund_no 退款订单号
     * @param $total_price 此订单支付金额
     * @param $refund_price 退款金额 
     * @param $config 支付配置文件信息
     */
    public function refund_query_order($trade_id, $order_no, $refund_no, $total_price, $refund_price, $config) {
        require_once "lib_china_mini/log.php";
        require_once "lib_china_mini/WxPay.Api.php";
        $input = new WxPayRefund();
        $input->SetTransaction_id($trade_id);
        $input->SetOut_trade_no($order_no);
        $input->SetOut_refund_no($refund_no);
        $input->SetTotal_fee($total_price);
        $input->SetRefund_fee($refund_price);
        $input->SetOp_user_id($config->GetMerchantId());
        return WxPayApi::refundQuery($config, $input);
    }

}
