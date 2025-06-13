<?php
class HutkoForm
{
    const API_CHECKOUT_URL = 'https://pay.hutko.org/api/checkout/url/';
    const TEST_MERCHANT_ID = 1700002;
    const TEST_MERCHANT_KEY = 'test';
    const RESPONCE_SUCCESS = 'success';
    const RESPONCE_FAIL = 'failure';
    const ORDER_SEPARATOR = '#';
    const SIGNATURE_SEPARATOR = '|';
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';

    public static function getSignature($data, $secretKey)
    {
        return sha1($secretKey . HutkoForm::SIGNATURE_SEPARATOR . base64_encode(json_encode(array('order' => $data))));
    }

    public static function isPaymentValid($hutkoSettings, $response , $base64_data, $sign)
    {
        if ($hutkoSettings['merchant_id'] != $response['merchant_id']) {
		
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }
		if (isset($response['response_signature_string'])){
			unset($response['response_signature_string']);
		}
		if (isset($response['signature'])){
			unset($response['signature']);
		}
	    if ($sign != sha1($hutkoSettings['secret_key'] . HutkoForm::SIGNATURE_SEPARATOR . $base64_data)) {
		    return 'An error has occurred during payment. Signature is not valid.';
	    }
        return true;
    }
}