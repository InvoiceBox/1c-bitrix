<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PriceMaths;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;
use CEventLog;

Loc::loadMessages(__FILE__);

class InvoiceBoxHandler extends PaySystem\ServiceHandler
{
    const PAYMENT_VERSION_2 = 'version_2';
    const PAYMENT_VERSION_3 = 'version_3';
    const URL = 'https://api.invoicebox.ru/v3/';
    const URL_CREATE_ORDER = 'billing/api/order/order';
    const STATUS_CREATED = 'created';
    const STATUS_CANCELED = 'canceled';
    
    const NOTIFICATION_ERROR_CODE = [
        'other' => 'out_of_service',
        'amount' => 'order_wrong_amount',
        'paid' => 'order_already_paid',
        'not_found' => 'order_not_found',
        'sign' => 'signature_error',
    ];
    
    const NDS_NO = 'NONE';
    const NDS_0 = 'RUS_VAT0';
    const NDS_10 = 'RUS_VAT10';
    const NDS_20 = 'RUS_VAT20';
    const NDS_10_110 = 'RUS_VAT110';
    const NDS_20_120 = 'RUS_VAT120';
    
    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return array
     */
    protected function getPreparedParams(Payment $payment, Request $request = null, $version = self::PAYMENT_VERSION_2)
    {
        if ($version == self::PAYMENT_VERSION_2) {
            $signatureValue = md5(
                $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_ID') .
                $this->getBusinessValue($payment, 'PAYMENT_ID') .
                $this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY') .
                $this->getBusinessValue($payment, 'PAYMENT_CURRENCY') .
                $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY')
            ); 
        }

        $extraParams = array();
        if (method_exists(parent, "getPreparedParams")) {
            $extraParams = parent::getPreparedParams($payment, $request);
        };

        $extraParams["PS_MODE"] = $this->service->getField('PS_MODE') ?: self::PAYMENT_VERSION_2;
        switch ($version) {
            case self::PAYMENT_VERSION_2:
                $extraParams["SIGNATURE_VALUE"] = $signatureValue;
                $extraParams["INVOICEBOX_API_KEY"] = $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY');
                $extraParams["URL"] = $this->getUrl($payment, 'pay');
                $extraParams["INVOICEBOX_NOTIFY_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_NOTIFY_2');
                break;
            case self::PAYMENT_VERSION_3:
                $extraParams["INVOICEBOX_MERCHANT_ID"] = $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_ID_V3');
                $extraParams["URL"] = $this->getUrl($payment, 'pay_v3');
                $extraParams["INVOICEBOX_VAT_RATE_BASKET"] = $this->getBusinessValue($payment, 'INVOICEBOX_VAT_RATE_BASKET');
                $extraParams["INVOICEBOX_VAT_RATE_DELIVERY"] = $this->getBusinessValue($payment, 'INVOICEBOX_VAT_RATE_DELIVERY');
                $extraParams["INVOICEBOX_NOTIFY_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_NOTIFY_3');
                break;
        }

        $extraParams["INVOICEBOX_RETURN_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL');
        $extraParams["INVOICEBOX_SUCCESS_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_SUCCESS');
        $extraParams["INVOICEBOX_CANCEL_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_CANCEL');
        $extraParams["BX_PAYSYSTEM_CODE"] = $payment->getPaymentSystemId();
        $paymentCollection = $payment->getCollection();

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();
        $extraParams["ORDER_PERSONAL_TYPE"] = $this->getBusinessValue($payment, 'BUYER_TYPE') ?: 'private';

        $extraParams["SUCCESS_PAY"] = $this->successPay($payment, $order);
        $extraParams["ORDER"] = print_r($order, 1);
        $extraParams["ORDERID"] = $order->getId();

        $extraParams["DELIVERY_PRICE"] = $order->getDeliveryPrice();

        // User params
        $props = \CSaleOrderPropsValue::GetOrderProps($order->getField('ID'));
        while ($row = $props->fetch()) {
            switch ($row['CODE']) {
                case 'FIO' :
                    $extraParams['BUYER_PERSON_NAME'] = $row['VALUE'];
                    break;
                case 'EMAIL' :
                    $extraParams['BUYER_PERSON_EMAIL'] = $row['VALUE'];
                    break;
                case 'PHONE' :
                    $extraParams['BUYER_PERSON_PHONE'] = $row['VALUE'];
                    break;
            }
        }
        
        //settings params
        if($name = $this->getBusinessValue($payment, 'BUYER_PERSON_NAME'))
            $extraParams['BUYER_PERSON_NAME'] = $name;
        if($email = $this->getBusinessValue($payment, 'BUYER_PERSON_EMAIL'))
            $extraParams['BUYER_PERSON_EMAIL'] = $email;
        if($phone = $this->getBusinessValue($payment, 'BUYER_PERSON_PHONE'))
            $extraParams['BUYER_PERSON_PHONE'] = $phone;
        if($inn = $this->getBusinessValue($payment, 'BUYER_PERSON_INN'))
            $extraParams['BUYER_PERSON_INN'] = $inn;
        if($registrationAddress = $this->getBusinessValue($payment, 'BUYER_PERSON_REGISTR_ADDRESS'))
            $extraParams['BUYER_PERSON_ADDRESS'] = $registrationAddress;
        return $extraParams;
    }

    protected function successPay($payment, $order)
    {
        if (!$this->isDefferedPayment() || ($this->isDefferedPayment() && $order->getField(
                    'STATUS_ID'
                ) == $this->isDefferedPayment())) {
            return true;
        }
        return false;
    }

    //Проверяем нужно ли подтверждение заказа
    protected function isDefferedPayment()
    {
        $options = \CSalePaySystemAction::GetList([], ['ACTION_FILE' => 'invoicebox'])->Fetch();
        if (!$options) {
            return true;
        }
        $params = unserialize($options['PARAMS']);
        if (!isset($params['PS_IS_DEFFERED_PAYMENT']) || empty($params['PS_IS_DEFFERED_PAYMENT']['VALUE'])) {
            return true;
        }
        return isset($params['PS_IS_DEFFERED_PAYMENT']) && !empty($params['PS_IS_DEFFERED_PAYMENT']['VALUE']) ? $params['PS_IS_DEFFERED_PAYMENT']['VALUE'] : false;
    }

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return PaySystem\ServiceResult
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        $version = $this->service->getField('PS_MODE') ?: self::PAYMENT_VERSION_2;
        /** @var \Bitrix\Sale\PaymentCollection $paymentCollection */
        $paymentCollection = $payment->getCollection();

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();

        $extraParams = $this->getPreparedParams($payment, $request, $version);
        $extraParams["BASKET_ITEMS"] = $order->getBasket();
        $extraParams["DELIVERY_PRICE"] = $order->getDeliveryPrice();
        
        $shipmentRes = \Bitrix\Sale\Shipment::getList(array(
			'select' => array('ID', ),
			'filter' => array(
				'ORDER_ID' => $extraParams["ORDERID"]
			),
			'order' => array('ID' => 'DESC'),
			'limit' => 1
		))->fetch();
        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->getItemById($shipmentRes["ID"]);
        $extraParams["DELIVERY_NAME"] = $shipment->getDeliveryName() ?: '';
        $extraParams["DELIVERY_ID"] = 'delivery_' . $shipment->getDeliveryID() ?: '';
        
        $this->setExtraParams($extraParams);

        switch ($version) {
            case self::PAYMENT_VERSION_2:
                return $this->showTemplate($payment, "template");
            case self::PAYMENT_VERSION_3:
                if ($request === null) {
                    $request = Context::getCurrent()->getRequest();
                }
                $result = $this->initiatePayInternal($payment, $request, $extraParams);
                if (!$result->isSuccess()) {
                    $error = 'Invoicebox v3: initiatePay order#'.$payment->getField('ORDER_ID').' :'.join('\n', $result->getErrorMessages());
                    PaySystem\Logger::addError($error);
                }
                return $result;
        }

    }
    
    private function initiatePayInternal(Payment $payment, Request $request, $params)
	{
		$result = new PaySystem\ServiceResult();

		$createResult = $this->createInvoicePayment($payment, $request, $params);
		if (!$createResult->isSuccess())
		{
			$result->addErrors($createResult->getErrors());
			return $result;
		}

		$paymentData = $createResult->getData()['data'];

		if ($paymentData['status'] === static::STATUS_CANCELED)
		{
			return $result->addError(
				new Error(
					Loc::getMessage('INVOICEBOX_ERROR_PAYMENT_CANCELED')
				)
			);
		}

		$result->setPsData(array('PS_INVOICE_ID' => $paymentData['id']));

		$params = array(
			'URL' => $paymentData['paymentUrl'],
			'PAYMENT_CURRENCY' => $payment->getField('CURRENCY'),
			'SUM' => PriceMaths::roundPrecision($payment->getSum()),
		);
		$this->setExtraParams($params);

		$template = "template";

		$showTemplateResult = $this->showTemplate($payment, 'template_v3');
		if ($showTemplateResult->isSuccess())
		{
			$result->setTemplate($showTemplateResult->getTemplate());
		}
		else
		{
			$result->addErrors($showTemplateResult->getErrors());
		}

		return $result;
	}
    
    /**
	 * @param Payment $payment
	 * @param Request $request
	 * @return PaySystem\ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 */
	private function createInvoicePayment(Payment $payment, Request $request, $params)
	{
		$result = new PaySystem\ServiceResult();

        $params['URL'] .= self::URL_CREATE_ORDER;
        
		$headers = $this->getHeaders($payment);
        $body = $this->getBodyRequest($payment, array_merge($this->getParamsBusValue($payment), $params));
	
		$sendResult = $this->send($params['URL'], $headers, $body);
        
		if (!$sendResult->isSuccess())
		{
			$result->addErrors($sendResult->getErrors());
			return $result;
		}

		$response = $sendResult->getData();
        $result->setData($response);

		return $result;
	}

    /**
	 * @param $url
	 * @param array $headers
	 * @param array $params
	 * @return PaySystem\ServiceResult
	 * @throws Main\ArgumentException
	 */
	private function send($url, array $headers, array $params = array())
	{
		$result = new PaySystem\ServiceResult();

		$httpClient = new HttpClient();
		foreach ($headers as $name => $value)
		{
			$httpClient->setHeader($name, $value);
		}

		$postData = null;
		if ($params)
		{
			$postData = static::encode($params);
		}

        if (class_exists('Bitrix\Sale\PaySystem\Logger'))
        {
            PaySystem\Logger::addDebugInfo('InvoiceBox: request data: '.$postData);
        }

		$response = $httpClient->post($url, $postData);

		$response = static::decode($response);

		$httpStatus = $httpClient->getStatus();

		if ($httpStatus >= 400 && isset($response['error']['code']))
		{
			$error = Loc::getMessage('INVOICEBOX_CODE_ERROR_'.$response['error']['code']);
			if ($error) {
				$result->addError(new Error($error));
			} else {
				$result->addError(new Error(Loc::getMessage('INVOICEBOX_ERROR')));
			}
		} elseif ($httpStatus == 200 && !empty($response['data']['paymentUrl']) && $response['data']['status'] == static::STATUS_CREATED) {
            $result->setData($response);
        }
        elseif (!empty($response['data']['status'])) {
            $result->addError(new Error(Loc::getMessage('INVOICEBOX_CODE_ERROR_'.$response['data']['status'])));
        }
        if (class_exists('Bitrix\Sale\PaySystem\Logger'))
		{
            PaySystem\Logger::addDebugInfo('InvoiceBox: response data: '.$response);
        }
        
		return $result;
	}
    
    /**
	 * @param array $data
	 * @return mixed
	 * @throws Main\ArgumentException
	 */
	private static function encode(array $data)
	{
        if(version_compare(phpversion(), '7.1', '>=')){
            ini_set('serialize_precision', -1);
        }
		return Json::encode($data, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNOR);
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private static function decode($data)
	{
		try
		{
			return Json::decode($data);
		}
		catch (Main\ArgumentException $exception)
		{
			return false;
		}
	}
    
    /**
	 * @param Payment $payment
	 * @return array
	 */
	private function getHeaders(Payment $payment)
	{
		$headers = [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getBusinessValue($payment, 'INVOICEBOX_AUTH_TOKEN'),
		];

		return $headers;
	}
    
    /**
	 * @param array $params
	 * @return array
	 */
	private function getBodyRequest(Payment $payment, array $params)
	{
        $vatAmount = 0;
		$body = [
			'merchantId' => $params['INVOICEBOX_MERCHANT_ID'],
			'merchantOrderId' => (string)$params['ORDERID'],
			'amount' => (float)htmlspecialcharsbx(number_format($params['PAYMENT_SHOULD_PAY'], 2, '.', '')),
			'successUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL_SUCCESS"]),
			'failUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL_CANCEL"]),
			'notificationUrl' => htmlspecialcharsbx($params["INVOICEBOX_NOTIFY_URL"]),
			'returnUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL"]),
            "currencyId" => $params['PAYMENT_CURRENCY'],
            "description" => htmlspecialcharsbx($params['INVOICEBOX_ORDERDESCR'] . " (#" . $params['ORDERID'] . ")"),
            "expirationDate" => FormatDate("c", time() + (86400 * 7)),
            "customer" => [
                'type' => $params['ORDER_PERSONAL_TYPE'] ?: 'private',
                'name' => $params['BUYER_PERSON_NAME'] ?? '',
                'email' => $params['BUYER_PERSON_EMAIL'] ?? '',
                'phone' => $params['BUYER_PERSON_PHONE'] ?? '',
                'vatNumber' => $params['BUYER_PERSON_INN'] ?? '',
                'registrationAddress' => $params['BUYER_PERSON_REGISTR_ADDRESS'] ?? '',
            ],
		];
           
        $index = 0;
        foreach ($params['BASKET_ITEMS'] as $basketItem) {
            $basketField = $basketItem->getFields();
            
            if ($params['INVOICEBOX_VAT_RATE_BASKET'] == 'SETTINGS_BASKET')
                $arDataWithNDS = self::getNDSData($basketItem, 'product');
            else
                $arDataWithNDS = self::calculateNDSData($basketItem, $params['INVOICEBOX_VAT_RATE_BASKET'], 'product');

            $body['basketItems'][$index++] = array_merge(
                [
                    'sku' => htmlspecialcharsbx($basketField['PRODUCT_ID']),
                    'name' => htmlspecialcharsbx($basketField['NAME']),
                    'measure' => $basketField['MEASURE_NAME'] ?: Loc::getMessage('MEASURE_NAME_DEFAULT'),
                    'measureCode' => (string)$basketField['MEASURE_CODE'] ?: Loc::getMessage('MEASURE_CODE_DEFAULT'),
                    'quantity' => (float)$basketField['QUANTITY'],
                    'amount' => (float)roundEx($basketItem->getPrice(), 2),
                    'totalAmount' => (float)roundEx($basketItem->getFinalPrice(), 2),
                    'type' => $params['INVOICEBOX_TYPE_BASKET'] ?? 'commodity',
                    'paymentType' => $params['INVOICEBOX_PAYMENT_TYPE'] ?? 'full_prepayment',
                ],
                $arDataWithNDS
            );
            $vatAmount += $arDataWithNDS['totalVatAmount'];
        }

        if (isset($params["DELIVERY_PRICE"]) && $params["DELIVERY_PRICE"] > 0) {
            $shipmentCollection = $payment->getCollection()->getOrder()->getShipmentCollection();
            foreach ($shipmentCollection as $shipment) {
                if($shipment->isSystem() || $shipment->getPrice() == 0)
                    continue;
                
                if ($params['INVOICEBOX_VAT_RATE_DELIVERY'] == 'SETTINGS_DELIVERY')
                    $arDataWithNDS = self::getNDSData($shipment, 'delivery');
                else
                    $arDataWithNDS = self::calculateNDSData($shipment, $params['INVOICEBOX_VAT_RATE_DELIVERY'], 'delivery');
            
                $body['basketItems'][$index++] = array_merge(
                    [
                        'sku' => htmlspecialcharsbx($params['DELIVERY_ID']),
                        'name' => htmlspecialcharsbx($params['DELIVERY_NAME']),
                        'measure' => Loc::getMessage('MEASURE_NAME_DEFAULT'),
                        'measureCode' => (string)Loc::getMessage('MEASURE_CODE_DEFAULT'),
                        'quantity' => 1,
                        'amount' => roundEx($shipment->getPrice(), 2),
                        'totalAmount' => roundEx($shipment->getPrice(), 2),
                        'type' => $params['INVOICEBOX_TYPE_DELIVERY'] ?? 'service',
                        'paymentType' => $params['INVOICEBOX_PAYMENT_TYPE'] ?? 'full_prepayment',
                    ],
                    $arDataWithNDS
                );
                $vatAmount += $arDataWithNDS['totalVatAmount'];
            }
        }
        
        $body['vatAmount'] = (float)roundEx($vatAmount, 2);

		return $body;
	}
    
    private static function getNDSData($item, $addType)
	{
        $arRes = [];
        if ($addType == 'product') {
            if (\Bitrix\Main\Loader::includeModule('catalog')) 
            {
                $arNDS  = \CCatalogProduct::GetVATInfo($item->getProductId())->Fetch();
                if (isset($arNDS['ID'])) {
                    $arRes['vatCode'] = self::convertNDSFromVatId($arNDS['ID']);
                } else {
                    $arRes['vatCode'] = self::NDS_NO;
                }
            } else {
                $arRes['vatCode'] = self::NDS_NO;
            }
            $arRes['totalVatAmount'] = (float)roundEx($item->getVat(), 2);
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() - ($item->getVat() / $item->getQuantity()), 2);
        } elseif($addType == 'delivery') {
            $delivery = \Bitrix\Sale\Delivery\Services\Manager::getById($item->getDeliveryId());
            if (empty($delivery['VAT_ID'])) 
            {
                $arRes['vatCode'] = self::NDS_NO;
            } else {
                $arRes['vatCode'] = self::convertNDSFromVatId($delivery['VAT_ID']);
            }

            $arRes['totalVatAmount'] = (float)roundEx($item->getVatSum(), 2);
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() - $item->getVatSum(), 2);
        }
        
        return $arRes;
	}
    
    private static function calculateNDSData($item, $vatCode, $addType)
    {
        $arRes = [];
        $rate = self::convertNDSFromVatCode($vatCode);

        $arRes['vatCode'] = $vatCode;
        if ($addType == 'product') {
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() / (1 + ($rate / 100)), 2);
            $arRes['totalVatAmount'] = (float)roundEx(($item->getPrice() - $arRes['amountWoVat']) * $item->getQuantity(), 2);
        } elseif($addType == 'delivery') {
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() / (1 + ($rate / 100)), 2);
            $arRes['totalVatAmount'] = (float)roundEx($item->getPrice() - $arRes['amountWoVat'], 2);
        }
        
        return $arRes;
	}
	
	private static function convertNDSFromVatId($vat)
	{
        $arNDS = \CCatalogVat::GetByID($vat)->Fetch();

        $rate = intval($arNDS['RATE']);
        switch($rate) 
        {
            case  0: 
                if(mb_strtolower($arNDS['NAME']) === GetMessage('INVOICEBOX_WITHOUT_NDS'))
                    $NDS = self::NDS_NO;
                else
                    $NDS = self::NDS_0;
                break;

            case 10:
                $NDS = self::NDS_10;
                break;

            case 20:
                $NDS = self::NDS_20;
                break;

            default: 
                $NDS = self::NDS_NO;
                break;
        }
        return $NDS;
	}
    
	private static function convertNDSFromVatCode($vatCode)
	{
        switch($vatCode) 
        {
            case self::NDS_NO:
            case self::NDS_0:
                $rate = 0;
                break;

            case self::NDS_10:
            case self::NDS_10_110:
                $rate = 10;
                break;

            case self::NDS_20:
            case self::NDS_20_120:
                $rate = 20;
                break;

            default: 
                $rate = 0;
                break;
        }
        return $rate;
	}
    
    
    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return array(
            "participantId",
            "participantOrderId",
            "ucode",
            "timetype",
            "time",
            "amount",
            "currency",
            "agentName",
            "agentPointName",
            "testMode",
            "sign"
        );
    }

    /**
     * @param Request $request
     * @param $paySystemId
     * @return bool
     */
    static protected function isMyResponseExtended(Request $request, $paySystemId)
    {
        return true;
    }
    
    /**
	 * @param Request $request
	 * @param int $paySystemId
	 * @return bool
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	public static function isMyResponse(Request $request, $paySystemId)
	{
        $paySystemResult = PaySystem\Manager::getList([
            'filter' => ['ID' => $paySystemId],
            'select' => ['PS_MODE']
        ])->fetch();
        
        if (empty($paySystemResult['PS_MODE'])) {
            $paySystemResult['PS_MODE'] = self::PAYMENT_VERSION_2;
        }
        
        if (
            !is_null($request->get("participantId"))
            && !is_null($request->get("participantOrderId"))
            && !is_null($request->get("ucode"))
            && !is_null($request->get("sign"))
            && $paySystemResult['PS_MODE'] === self::PAYMENT_VERSION_2
        ){
            return true;
        } else {
            $inputStream = static::readFromStream();
            if(static::isJSON($inputStream) && $paySystemResult['PS_MODE'] === self::PAYMENT_VERSION_3) {
                if (static::decode($inputStream) === false) {
                    return false;
                }
                return true;
            }
        }

		return false;
	}
    
    private static function isJSON( $string ){
        $result = json_decode($string) ;
        return ( json_last_error() === JSON_ERROR_NONE) ;
    }

	/**
	 * @return bool|string
	 */
	private static function readFromStream()
	{
		return file_get_contents('php://input');
	}

    /**
     * @param Payment $payment
     * @param $request
     * @param $version
     * @return bool
     */
    private function isCorrectHash(Payment $payment, Request $request, $version)
    {
        switch ($version) {
            case self::PAYMENT_VERSION_2:
                $apiKey = $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY');
                if (empty($apiKey)) {
                    CEventLog::Add(
                        [
                            'SEVERITY' => 'INFO',
                            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                            'MODULE_ID' => 'invoicebox.payment',
                            'DESCRIPTION' => json_encode(
                                [
                                    'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_SET_API_KEY')
                                ],
                                true
                            ),
                        ]
                    );
                    return false;
                }

                // Sign type A
                $sign_strA =
                    $request->get("participantId") .
                    $request->get("participantOrderId") .
                    $request->get("ucode") .
                    $request->get("timetype") .
                    $request->get("time") .
                    $request->get("amount") .
                    $request->get("currency") .
                    $request->get("agentName") .
                    $request->get("agentPointName") .
                    $request->get("testMode") .
                    $apiKey;

                $sign_crcA = md5($sign_strA);

                if (ToUpper($sign_crcA) == ToUpper($request->get('sign'))) {
                    return true;
                } else {
                    CEventLog::Add(
                        [
                            'SEVERITY' => 'INFO',
                            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                            'MODULE_ID' => 'invoicebox.payment',
                            'DESCRIPTION' => json_encode(
                                [
                                    'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_API_KEY_IN_REQUEST')
                                ],
                                true
                            ),
                        ]
                    );
                    return false;
                }
            case self::PAYMENT_VERSION_3:
                $inputStream = static::readFromStream();
                $apiKey = $this->getBusinessValue($payment, 'INVOICEBOX_NOTIFICATION_TOKEN_V3');
                if (empty($apiKey)) {
                   return false;
                }
                $sign = hash_hmac('sha1', $inputStream, $apiKey);
                $headers = getallheaders();
                if ($sign === $headers['X-Signature']) {
                    return true;
                }
                return false;
        }
        
    }

    /**
     * @param Payment $payment
     * @param float $amount
     * @return bool
     */
    private function isCorrectSum(Payment $payment, float $amount)
    {
        $sum = PriceMaths::roundByFormatCurrency($amount, $payment->getField('CURRENCY'));
        $paymentSum = PriceMaths::roundByFormatCurrency(
            $this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY'),
            $payment->getField('CURRENCY')
        );
        
        if ($paymentSum == $sum) {
            return true;
        } else {
            CEventLog::Add(
                [
                    'SEVERITY' => 'INFO',
                    'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                    'MODULE_ID' => 'invoicebox.payment',
                    'DESCRIPTION' => json_encode(
                        [
                            'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_REQUEST_AMOUNT_IS_NOT_VALID')
                        ],
                        true
                    ),
                ]
            );
            return false;
        }
    }
    
    /**
     * @param Payment $payment
     * @return bool
     */
    private function isNotPaid(Payment $payment)
    {
        if ($payment->isPaid()) {
            if ($this->service->getField('PAY_SYSTEM_ID') !== $payment->getPaySystem()) {
                CEventLog::Add(
                    [
                        'SEVERITY' => 'INFO',
                        'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                        'MODULE_ID' => 'invoicebox.payment',
                        'DESCRIPTION' => json_encode(
                            [
                                'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_REQUEST_IS_OTHER_PAYMENT_PAYED')
                            ],
                            true
                        ),
                    ]
                );
                return false;
            }
        }
        return true;
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        if (!is_null($request->get('participantOrderId'))) {
        return $request->get('participantOrderId');
        } else {
            $inputStream = static::readFromStream();
            if ($inputStream && static::isJSON($inputStream))
            {
                $data = static::decode($inputStream);
                if ($data === false)
                {
                    return false;
                }

                return $data['merchantOrderId'];

            }

            return false;
        }
    }

    /**
     * @return mixed
     */
    protected function getUrlList()
    {
        return [
            'pay' => [
                self::ACTIVE_URL => 'https://go.invoicebox.ru/module_inbox_auto.u'
            ],
            'pay_v3' => [
                self::ACTIVE_URL => self::URL
            ]
        ];
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {
        $version = $this->service->getField('PS_MODE') ?: self::PAYMENT_VERSION_2;
        $result = new PaySystem\ServiceResult();

        if ($this->isCorrectHash($payment, $request, $version)) {
            return $this->processNoticeAction($payment, $request, $version);
        } else {
            PaySystem\ErrorLog::add(
                array(
                    'ACTION' => 'processRequest',
                    'MESSAGE' => 'Incorrect hash'
                )
            );
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['sign']));
        }

        return $result;
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    private function processNoticeAction(Payment $payment, Request $request, $version)
    {
        $result = new PaySystem\ServiceResult();
        $amount = 0;
        $curr = 'RUB';
        $bPay = false;

        switch ($version) {
            case self::PAYMENT_VERSION_2:
                $psStatusDescription = Loc::getMessage('SALE_HPS_INVOICEBOX_RES_NUMBER') . ": " . $request->get(
                        'participantOrderId'
                    ) . " (" . $request->get('ucode') . ")";
                $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_DATEPAY') . ": " . date("d.m.Y H:i:s");
                $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_PAY_TYPE') . ": " . $request->get(
                        "agentName"
                    );
                $amount = $request->get('amount');
                $curr = $this->getBusinessValue($payment, "PAYMENT_CURRENCY");
                $bPay = true;
                break;
            case self::PAYMENT_VERSION_3:
                $inputStream = static::readFromStream();
                $data = static::decode($inputStream);
                $psStatusDescription = Loc::getMessage('SALE_HPS_INVOICEBOX_RES_NUMBER') . ": " . $data['merchantOrderId'] . " (" . $data['id'] . ")";
                $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_DATEPAY') . ": " . date("d.m.Y H:i:s");
                $amount = $data['amount'];
                $curr = $data['currencyId'];
                if ($data['status'] === 'completed') {
                    $bPay = true;
                }
                break;
        }
        
        $fields = array(
            "PS_STATUS" => "Y",
            "PS_STATUS_CODE" => "-",
            "PS_STATUS_DESCRIPTION" => $psStatusDescription,
            "PS_STATUS_MESSAGE" => Loc::getMessage('SALE_HPS_INVOICEBOX_RES_PAYED'),
            "PS_SUM" => $amount,
            "PS_CURRENCY" => $curr,
            "PS_RESPONSE_DATE" => new DateTime(),
        );

        $result->setPsData($fields);

        if ($this->isCorrectSum($payment, $amount)) {
            if ($version === self::PAYMENT_VERSION_3 && !$this->isNotPaid($payment)) {
                $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['paid']));
            } else {
                $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
            }
        } else {
            PaySystem\ErrorLog::add(
                array(
                    'ACTION' => 'processNoticeAction',
                    'MESSAGE' => 'Incorrect sum'
                )
            );
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['amount']));
        }

        return $result;
    }  

    /**
     * @param Payment $payment
     * @return bool
     */
    protected function isTestMode(Payment $payment = null)
    {
        return ($this->getBusinessValue($payment, 'PS_IS_TEST') == 'Y');
    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return array('RUB');
    }

    /**
     * @param PaySystem\ServiceResult $result
     * @param Request $request
     * @return mixed
     */
    public function sendResponse(PaySystem\ServiceResult $result, Request $request)
    {
        global $APPLICATION;
        if ($result->isResultApplied()) {
            $APPLICATION->RestartBuffer();
            echo 'OK';
        };
    }

    /**
     * @return array
     */
    public static function getHandlerModeList()
    {
        return array(
            static::PAYMENT_VERSION_2 => Loc::getMessage('SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_2'),
			static::PAYMENT_VERSION_3 => Loc::getMessage('SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_3'),
        );
    }
}
