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
    const VERSION = '2.0.12';
    const VERSION_UNKNOWN = 'unknown';

    const PAYMENT_VERSION_2 = 'version_2';
    const URL_v2 = 'https://go.invoicebox.ru/module_inbox_auto.u';

    const PAYMENT_VERSION_3 = 'version_3';
    const URL_v3 = 'https://api.invoicebox.ru/v3/';

    const URL_CREATE_ORDER = 'billing/api/order/order';
    const STATUS_CREATED = 'created';
    const STATUS_CANCELED = 'canceled';
    const STATUS_COMPLETED = 'completed';

    const PERSON_TYPE_ORDER = 'order';
    const PERSON_TYPE_PRIVATEID = 1;
    const PERSON_TYPE_PRIVATE = 'private';
    const PERSON_TYPE_LEGALID = 2;
    const PERSON_TYPE_LEGAL = 'legal';

    const OBJECT_TYPE_FIELD = 'IB_OBJECT_TYPE';

    const NOTIFICATION_ERROR_CODE = [
        'other' => 'out_of_service',
        'amount' => 'order_wrong_amount',
        'currency' => 'order_wrong_currency',
        'paid' => 'order_already_paid',
        'not_found' => 'order_not_found',
        'sign' => 'signature_error',
    ];

    const TEST_ORDER = [
        '00000-00000-00000-00000',
        'ffffffff-ffff-ffff-ffff-ffffffffffff'
    ];

    const VAT_NO = 'NONE';
    const VAT_0 = 'RUS_VAT0';
    const VAT_10 = 'RUS_VAT10';
    const VAT_20 = 'RUS_VAT20';
    const VAT_10_110 = 'RUS_VAT110';
    const VAT_20_120 = 'RUS_VAT120';

    const VATRATE_NO = 6;
    const VATRATE_0 = 5;
    const VATRATE_10_110 = 4;
    const VATRATE_20_120 = 3;
    const VATRATE_10 = 2;
    const VATRATE_20 = 1;

    protected function parentMethodExists($object, $method)
    {
        foreach (class_parents($object) as $parent)
        {
            if (method_exists($parent, $method))
            {
               return true;
            }
        }

        return false;
    }

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @param string $version
     * @return array
     */
    protected function getPreparedParams(
        Payment $payment,
        Request $request = null,
        $version = self::PAYMENT_VERSION_2
    ): array {
        $signatureValue = "";
        if ($version == self::PAYMENT_VERSION_2) {
            $signatureValue = md5(
                $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_ID') .
                $this->getBusinessValue($payment, 'PAYMENT_ID') .
                $this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY') .
                $this->getBusinessValue($payment, 'PAYMENT_CURRENCY') .
                $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY')
            );
        }

        $extraParams = [];
        if ($this->parentMethodExists($this, "getPreparedParams")) {
            $extraParams = parent::getPreparedParams($payment, $request);
        }

        $extraParams["PS_MODE"] = $this->service->getField('PS_MODE') ?: self::PAYMENT_VERSION_2;
        switch ($version) {
            case self::PAYMENT_VERSION_2:
                $extraParams["SIGNATURE_VALUE"] = $signatureValue;
                $extraParams["INVOICEBOX_API_KEY"] = $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY');
                $extraParams["URL"] = $this->getUrl($payment, $version);
                $extraParams["INVOICEBOX_NOTIFY_URL"] = $this->getBusinessValue(
                    $payment,
                    'INVOICEBOX_RETURN_URL_NOTIFY_2'
                );
                break;
            case self::PAYMENT_VERSION_3:
                $extraParams["INVOICEBOX_MERCHANT_ID"] = $this->getBusinessValue(
                    $payment,
                    'INVOICEBOX_PARTICIPANT_ID_V3'
                );
                $extraParams["URL"] = $this->getUrl($payment, $version);
                $extraParams["INVOICEBOX_NOTIFY_URL"] = $this->getBusinessValue(
                    $payment,
                    'INVOICEBOX_RETURN_URL_NOTIFY_3'
                );
                break;
        }

        $extraParams["INVOICEBOX_VAT_RATE_BASKET"] = $this->getBusinessValue($payment, 'INVOICEBOX_VAT_RATE_BASKET');
        $extraParams["INVOICEBOX_VAT_RATE_DELIVERY"] = $this->getBusinessValue(
            $payment,
            'INVOICEBOX_VAT_RATE_DELIVERY'
        );
        $extraParams["INVOICEBOX_TYPE_DELIVERY"] = $this->getBusinessValue($payment, 'INVOICEBOX_TYPE_DELIVERY');
        $extraParams["INVOICEBOX_TYPE_BASKET"] = $this->getBusinessValue($payment, 'INVOICEBOX_TYPE_BASKET');
        $extraParams["INVOICEBOX_RETURN_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL');
        $extraParams["INVOICEBOX_SUCCESS_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_SUCCESS');
        $extraParams["INVOICEBOX_CANCEL_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_CANCEL');

        $extraParams["BX_PAYSYSTEM_CODE"] = $payment->getPaymentSystemId();
        $paymentCollection = $payment->getCollection();

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();

        $extraParams["ORDER_PERSONAL_TYPE_ID"] = $order->getField("PERSON_TYPE_ID");
        $extraParams["ORDER_PERSONAL_TYPE"] = $this->getBusinessValue(
            $payment,
            'BUYER_TYPE'
        ) ?: self::PERSON_TYPE_ORDER;
        if ($extraParams["ORDER_PERSONAL_TYPE"] == self::PERSON_TYPE_ORDER) {
            switch ($extraParams["ORDER_PERSONAL_TYPE_ID"]) {
                case self::PERSON_TYPE_PRIVATEID:
                    $extraParams["ORDER_PERSONAL_TYPE"] = self::PERSON_TYPE_PRIVATE;
                    break;
                case self::PERSON_TYPE_LEGALID:
                    $extraParams["ORDER_PERSONAL_TYPE"] = self::PERSON_TYPE_LEGAL;
                    break;
                default:
                    $extraParams["ORDER_PERSONAL_TYPE"] = self::PERSON_TYPE_PRIVATE;
                    break;
            } //
        } //

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
        if ($name = $this->getBusinessValue($payment, 'BUYER_PERSON_NAME')) {
            $extraParams['BUYER_PERSON_NAME'] = $name;
        }
        if ($email = $this->getBusinessValue($payment, 'BUYER_PERSON_EMAIL')) {
            $extraParams['BUYER_PERSON_EMAIL'] = $email;
        }
        if ($phone = $this->getBusinessValue($payment, 'BUYER_PERSON_PHONE')) {
            $extraParams['BUYER_PERSON_PHONE'] = $phone;
        }
        if ($inn = $this->getBusinessValue($payment, 'BUYER_PERSON_INN')) {
            $extraParams['BUYER_PERSON_INN'] = $inn;
        }
        if ($registrationAddress = $this->getBusinessValue($payment, 'BUYER_PERSON_REGISTR_ADDRESS')) {
            $extraParams['BUYER_PERSON_ADDRESS'] = $registrationAddress;
        }
        return $extraParams;
    }

    protected function successPay($payment, $order): bool
    {
        return !$this->isDefferedPayment() || ($this->isDefferedPayment() && $order->getField(
                    'STATUS_ID'
                ) == $this->isDefferedPayment());
    }

    // Проверяем нужно ли подтверждение заказа
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

    public function getUserAgent(): string
    {
        return 'Bitrix/' . (defined('SM_VERSION') ? constant(
                "SM_VERSION"
            ) : self::VERSION_UNKNOWN) . ' (Invoicebox ' . self::VERSION . ')';
    }

    public static function getIblockElement($iblockElementId): array
    {
        $arOrder = [];
        $arFilter = ['ID' => $iblockElementId];
        $arGroupBy = false;
        $arNavStartParams = false;
        $arSelectFields = ['ID', '*'];

        $dbRes = \CIBlockElement::GetList(
            $arOrder,
            $arFilter,
            $arGroupBy,
            $arNavStartParams,
            $arSelectFields
        );

        $element = $dbRes->Fetch();

        $propsDbres = \CIBlockElement::GetProperty(
            $element['IBLOCK_ID'],
            $iblockElementId,
            "sort",
            "asc",
            [">ID" => 1]
        );

        $i = 0;
        while ($prop = $propsDbres->GetNext()) {
            $i = !isset(
                $element['PROPS'][$prop['CODE']]
            ) ? 0 : $i + 1;
            $element['PROPS'][$prop['CODE']]['NAME'] = $prop['NAME'];
            $element['PROPS'][$prop['CODE']]['TYPE'] = $prop['PROPERTY_TYPE'];
            $element['PROPS'][$prop['CODE']]['ACTIVE'] = $prop['ACTIVE'];

            $element['PROPS'][$prop['CODE']]['VALUES'][$i] = [
                'VALUE' => $prop['VALUE'],
                'DESCRIPTION' => $prop['DESCRIPTION'],
            ];

            if ($prop['PROPERTY_TYPE'] == 'F') {
                $element['PROPS'][$prop['CODE']]['VALUE'][$i]['PATH'] = \CFile::GetPath((int)$prop['VALUE']);
            }
        }

        return $element;
    }

    public function getBasketItemProductPropValue($basketItem, $propIdent): string
    {
        \Bitrix\Main\Loader::IncludeModule("catalog");
        \Bitrix\Main\Loader::IncludeModule("iblock");

        $result = "";

        $product = $basketItem->getFieldValues();
        $mxResult = \CCatalogSku::GetProductInfo($product["PRODUCT_ID"]);
        $productId = ($mxResult ? $mxResult['ID'] : $product["PRODUCT_ID"]);

        $iBlock = $this->getIblockElement($productId);
        $propertyId = isset($iBlock["PROPS"][$propIdent], $iBlock["PROPS"][$propIdent]["VALUES"][0]["VALUE"]) ?
            $iBlock["PROPS"][$propIdent]["VALUES"][0]["VALUE"] : false;

        $properties = \CIBlockProperty::GetList(
            ["sort" => "asc", "name" => "asc"],
            [
                "ACTIVE" => "Y",
                "CODE" => $propIdent,
                "PROPERTY_TYPE" => "L",
                "IBLOCK_ID" => ($mxResult ? $mxResult["IBLOCK_ID"] : $iBlock["IBLOCK_ID"])
            ]
        ); //

        while ($ob = $properties->GetNext()) {
            $ibpenum = new \CIBlockPropertyEnum;
            $enum = $ibpenum->GetList(["sort" => "asc", "name" => "asc"], ["ID" => $propertyId]); //
            while ($en = $enum->GetNext()) {
                $result = $en["XML_ID"];
                break;
            } //
        } //

        return $result;
    }

    public function setPreparedBasketItems($payment, $paymentCollection, &$extraParams): array
    {
        $result = [];

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();

        $extraParams["USER_AGENT"] = $this->getUserAgent();
        $extraParams["BASKET_ITEMS"] = $order->getBasket();
        $extraParams["DELIVERY_PRICE"] = $order->getDeliveryPrice();

        $shipmentRes = \Bitrix\Sale\Shipment::getList([
                                                          'select' => ['ID',],
                                                          'filter' => [
                                                              'ORDER_ID' => $extraParams["ORDERID"]
                                                          ],
                                                          'order' => ['ID' => 'DESC'],
                                                          'limit' => 1
                                                      ])->fetch();
        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->getItemById($shipmentRes["ID"]);
        $extraParams["DELIVERY_NAME"] = $shipment->getDeliveryName() ?: '';
        $extraParams["DELIVERY_ID"] = 'delivery_' . $shipment->getDeliveryID() ?: '';

        $totalVat = 0;
        $totalAmount = 0;
        $index = 0;

        foreach ($extraParams['BASKET_ITEMS'] as $basketItem) {
            $basketField = $basketItem->getFields();

            $objectType = $this->getBasketItemProductPropValue($basketItem, self::OBJECT_TYPE_FIELD);
            $objectType = ($objectType ?: ($extraParams['INVOICEBOX_TYPE_BASKET'] ?? 'commodity'));

            if ($extraParams['INVOICEBOX_VAT_RATE_BASKET'] == 'SETTINGS_BASKET') {
                $arDataWithVAT = self::getVATData($basketItem, 'product');
            } else {
                $arDataWithVAT = self::calculateVATData(
                    $basketItem,
                    $extraParams['INVOICEBOX_VAT_RATE_BASKET'],
                    'product'
                );
            }

            $amount = number_format((float)roundEx($basketItem->getFinalPrice(), 2), 2, '.', '');
            $result[$index++] = array_merge(
                [
                    'sku' => htmlspecialcharsbx($basketField['PRODUCT_ID']),
                    'name' => htmlspecialcharsbx($basketField['NAME']),
                    'measure' => $basketField['MEASURE_NAME'] ?: Loc::getMessage('MEASURE_NAME_DEFAULT'),
                    'measureCode' => (string)$basketField['MEASURE_CODE'] ?: Loc::getMessage('MEASURE_CODE_DEFAULT'),
                    'quantity' => (float)$basketField['QUANTITY'],
                    'amount' => number_format((float)roundEx($basketItem->getPrice(), 2), 2, '.', ''),
                    'totalAmount' => $amount,
                    'type' => $objectType,
                    'paymentType' => $extraParams['INVOICEBOX_PAYMENT_TYPE'] ?? 'full_prepayment',
                ],
                $arDataWithVAT
            );
            $totalVat += $arDataWithVAT['totalVatAmount'];
            $totalAmount += $amount;
        }

        if (isset($extraParams["DELIVERY_PRICE"]) && $extraParams["DELIVERY_PRICE"] > 0) {
            $shipmentCollection = $payment->getCollection()->getOrder()->getShipmentCollection();
            foreach ($shipmentCollection as $shipment) {
                if ($shipment->isSystem() || $shipment->getPrice() == 0) {
                    continue;
                }

                if ($extraParams['INVOICEBOX_VAT_RATE_DELIVERY'] == 'SETTINGS_DELIVERY') {
                    $arDataWithVAT = self::getVATData($shipment, 'delivery');
                } else {
                    $arDataWithVAT = self::calculateVATData(
                        $shipment,
                        $extraParams['INVOICEBOX_VAT_RATE_DELIVERY'],
                        'delivery'
                    );
                }

                $amount = number_format(roundEx($shipment->getPrice(), 2), 2, '.', '');
                $result[$index++] = array_merge(
                    [
                        'sku' => htmlspecialcharsbx($extraParams['DELIVERY_ID']),
                        'name' => htmlspecialcharsbx($extraParams['DELIVERY_NAME']),
                        'measure' => Loc::getMessage('MEASURE_NAME_DEFAULT'),
                        'measureCode' => (string)Loc::getMessage('MEASURE_CODE_DEFAULT'),
                        'quantity' => 1,
                        'amount' => $amount,
                        'totalAmount' => $amount,
                        'type' => $extraParams['INVOICEBOX_TYPE_DELIVERY'] ?? 'service',
                        'paymentType' => $extraParams['INVOICEBOX_PAYMENT_TYPE'] ?? 'full_prepayment',
                    ],
                    $arDataWithVAT
                );

                $totalVat += $arDataWithVAT['totalVatAmount'];
                $totalAmount += $amount;
            }
        }

        $extraParams['BASKET_ITEMS_PREPARED'] = $result;
        $extraParams['BASKET_TOTAL_AMOUNT'] = $totalAmount;
        $extraParams['BASKET_TOTAL_VAT'] = $totalVat;

        return $result;
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

        $extraParams = $this->getPreparedParams($payment, $request, $version);

        $this->setPreparedBasketItems($payment, $paymentCollection, $extraParams);

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
                    $error = 'Invoicebox v3: initiatePay order#' . $payment->getField('ORDER_ID') . ' :' . implode(
                            '\n',
                            $result->getErrorMessages()
                        );
                    PaySystem\Logger::addError($error);
                }
                return $result;
        }
    }

    private function initiatePayInternal(Payment $payment, Request $request, $params)
    {
        $result = new PaySystem\ServiceResult();

        $createResult = $this->createInvoicePayment($payment, $request, $params);
        if (!$createResult->isSuccess()) {
            $result->addErrors($createResult->getErrors());
            return $result;
        }

        $paymentData = $createResult->getData()['data'];

        if ($paymentData['status'] === static::STATUS_CANCELED) {
            return $result->addError(
                new Error(
                    Loc::getMessage('INVOICEBOX_ERROR_PAYMENT_CANCELED')
                )
            );
        }

        $result->setPsData(['PS_INVOICE_ID' => $paymentData['id']]);

        $params = [
            'URL' => $paymentData['paymentUrl'],
            'PAYMENT_CURRENCY' => $payment->getField('CURRENCY'),
            'SUM' => PriceMaths::roundPrecision($payment->getSum()),
        ];
        $this->setExtraParams($params);

        $showTemplateResult = $this->showTemplate($payment, 'template_v3');
        if ($showTemplateResult->isSuccess()) {
            $result->setTemplate($showTemplateResult->getTemplate());
        } else {
            $result->addErrors($showTemplateResult->getErrors());
        }

        return $result;
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @param $params
     * @return PaySystem\ServiceResult
     */
    private function createInvoicePayment(Payment $payment, Request $request, $params)
    {
        $result = new PaySystem\ServiceResult();

        $params['URL'] .= self::URL_CREATE_ORDER;

        $headers = $this->getHeaders_v3($payment);
        $body = $this->getBodyRequest_v3($payment, array_merge($this->getParamsBusValue($payment), $params));

        $sendResult = $this->send($params['URL'], $headers, $body);

        if (!$sendResult->isSuccess()) {
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
    private function send($url, array $headers, array $params = [])
    {
        $result = new PaySystem\ServiceResult();

        $httpClient = new HttpClient();
        foreach ($headers as $name => $value) {
            $httpClient->setHeader($name, $value);
        }

        $postData = null;
        if ($params) {
            $postData = static::encode($params);
        }

        if (class_exists('Bitrix\Sale\PaySystem\Logger')) {
            PaySystem\Logger::addDebugInfo('Invoicebox: request data: ' . $postData);
        }

        $response = $httpClient->post($url, $postData);

        $response = static::decode($response);

        $httpStatus = $httpClient->getStatus();

        if ($httpStatus >= 400 && isset($response['error']['code'])) {
            $error = Loc::getMessage('INVOICEBOX_CODE_ERROR_' . $response['error']['code']);
            if ($error) {
                $result->addError(new Error($error));
            } else {
                $result->addError(new Error(Loc::getMessage('INVOICEBOX_ERROR')));
            }
        } elseif ($httpStatus == 200 && !empty($response['data']['paymentUrl']) && $response['data']['status'] == static::STATUS_CREATED) {
            $result->setData($response);
        } elseif (!empty($response['data']['status'])) {
            $result->addError(new Error(Loc::getMessage('INVOICEBOX_CODE_ERROR_' . $response['data']['status'])));
        }
        if (class_exists('Bitrix\Sale\PaySystem\Logger')) {
            PaySystem\Logger::addDebugInfo('Invoicebox: response data: ' . $response);
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
        if (PHP_VERSION >= 70100) {
            ini_set('serialize_precision', -1);
        }
        return Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * @param string $data
     * @return mixed
     */
    private static function decode(string $data)
    {
        try {
            return Json::decode($data);
        } catch (Main\ArgumentException $exception) {
            return false;
        }
    }

    /**
     * @param Payment $payment
     * @return array
     */
    private function getHeaders_v3(Payment $payment): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => $this->getUserAgent(),
            'Authorization' => 'Bearer ' . $this->getBusinessValue($payment, 'INVOICEBOX_AUTH_TOKEN'),
        ];
    }

    /**
     * @param Payment $payment
     * @param array $params
     * @return array
     */
    private function getBodyRequest_v3(Payment $payment, array $params): array
    {
        return [
            'merchantId' => $params['INVOICEBOX_MERCHANT_ID'],
            'merchantOrderId' => (string)$params['ORDERID'],
            'amount' => (float)htmlspecialcharsbx(number_format($params['PAYMENT_SHOULD_PAY'], 2, '.', '')),
            'vatAmount' => (float)htmlspecialcharsbx(number_format($params['BASKET_TOTAL_VAT'], 2, '.', '')),
            'successUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL_SUCCESS"]),
            'failUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL_CANCEL"]),
            'notificationUrl' => htmlspecialcharsbx($params["INVOICEBOX_NOTIFY_URL"]),
            'returnUrl' => htmlspecialcharsbx($params["INVOICEBOX_RETURN_URL"]),
            "currencyId" => $params['PAYMENT_CURRENCY'],
            "description" => htmlspecialcharsbx($params['INVOICEBOX_ORDERDESCR'] . " (#" . $params['ORDERID'] . ")"),
            "expirationDate" => FormatDate("c", time() + (86400 * 7)),
            "customer" => [
                'type' => $params['ORDER_PERSONAL_TYPE'] ?: self::PERSON_TYPE_PRIVATE,
                'name' => $params['BUYER_PERSON_NAME'] ?? '',
                'email' => $params['BUYER_PERSON_EMAIL'] ?? '',
                'phone' => $params['BUYER_PERSON_PHONE'] ?? '',
                'vatNumber' => $params['BUYER_PERSON_INN'] ?? '',
                'registrationAddress' => $params['BUYER_PERSON_REGISTR_ADDRESS'] ?? '',
            ],
            "basketItems" => $params['BASKET_ITEMS_PREPARED'],
        ];
    }

    private static function getVATData($item, $addType): array
    {
        $arRes = [];
        $arRes['vatCode'] = self::VAT_NO;
        $arRes['vatRate'] = self::VATRATE_NO;

        if ($addType == 'product') {
            if (\Bitrix\Main\Loader::includeModule('catalog')) {
                $arVAT = \CCatalogProduct::GetVATInfo($item->getProductId())->Fetch();
                if (isset($arVAT['ID'])) {
                    $vatCode = $arRes['vatCode'];
                    $vatRate = $arRes['vatRate'];
                    self::convertVATFromVatId($arVAT['ID'], $vatRate, $vatCode);
                    $arRes['vatCode'] = $vatCode;
                    $arRes['vatRate'] = $vatRate;
                }
            }

            $arRes['totalVatAmount'] = (float)roundEx($item->getVat(), 2);
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() - ($item->getVat() / $item->getQuantity()), 2);
        } elseif ($addType == 'delivery') {
            $delivery = \Bitrix\Sale\Delivery\Services\Manager::getById($item->getDeliveryId());
            if (!empty($delivery['VAT_ID'])) {
                $vatCode = $arRes['vatCode'];
                $vatRate = $arRes['vatRate'];
                self::convertVATFromVatId($delivery['VAT_ID'], $vatRate, $vatCode);
                $arRes['vatCode'] = $vatCode;
                $arRes['vatRate'] = $vatRate;
            }

            $arRes['totalVatAmount'] = (float)roundEx($item->getVatSum(), 2);
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() - $item->getVatSum(), 2);
        }

        return $arRes;
    }

    private static function calculateVATData($item, $vatCode, $addType): array
    {
        $arRes = [];
        $vatRate = self::VATRATE_NO;
        $rate = 0;
        self::convertVATFromVatCode($vatCode, $vatRate, $rate);

        $arRes['vatCode'] = $vatCode;
        $arRes['vatRate'] = $vatRate;
        if ($addType == 'product') {
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() / (1 + ($rate / 100)), 2);
            $arRes['totalVatAmount'] = (float)roundEx(
                ($item->getPrice() - $arRes['amountWoVat']) * $item->getQuantity(),
                2
            );
        } elseif ($addType == 'delivery') {
            $arRes['amountWoVat'] = (float)roundEx($item->getPrice() / (1 + ($rate / 100)), 2);
            $arRes['totalVatAmount'] = (float)roundEx($item->getPrice() - $arRes['amountWoVat'], 2);
        }

        return $arRes;
    }

    private static function convertVATFromVatId($vatId, &$vatRate, &$vatCode)
    {
        $arVAT = \CCatalogVat::GetByID($vatId)->Fetch();

        $rate = (int)$arVAT['RATE'];
        switch ($rate) {
            case  0:
                if (mb_strtolower($arVAT['NAME']) === GetMessage('INVOICEBOX_WITHOUT_VAT')) {
                    $vatCode = self::VAT_NO;
                    $vatRate = self::VATRATE_NO;
                } else {
                    $vatCode = self::VAT_0;
                    $vatRate = self::VATRATE_0;
                }
                break;

            case 10:
                $vatCode = self::VAT_10;
                $vatRate = self::VATRATE_10_110;
                break;

            case 20:
                $vatCode = self::VAT_20;
                $vatRate = self::VATRATE_20_120;
                break;

            default:
                $vatCode = self::VAT_NO;
                $vatRate = self::VATRATE_NO;
                break;
        }
    }

    private static function convertVATFromVatCode($vatCode, &$vatRate, &$rate)
    {
        $rate = 0;
        $vatRate = self::VATRATE_NO;

        switch ($vatCode) {
            case self::VAT_NO:
                break;
            case self::VAT_0:
                $rate = 0;
                $vatRate = self::VATRATE_0;
                break;
            case self::VAT_10:
            case self::VAT_10_110:
                $rate = 10;
                $vatRate = self::VATRATE_10_110;
                break;

            case self::VAT_20:
            case self::VAT_20_120:
                $rate = 20;
                $vatRate = self::VATRATE_20_120;
                break;

            default:
                $rate = 0;
                $vatRate = self::VATRATE_NO;
                break;
        }
    }


    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return [
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
        ];
    }

    /**
     * @param Request $request
     * @param $paySystemId
     * @return bool
     */
    protected static function isMyResponseExtended(Request $request, $paySystemId)
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
        ) {
            return true;
        }

        $inputStream = static::readFromStream();
        if (static::isJSON($inputStream) && $paySystemResult['PS_MODE'] === self::PAYMENT_VERSION_3) {
            return !(static::decode($inputStream) === false);
        }

        return false;
    }

    private static function isJSON($string)
    {
        $result = json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
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
     * @param Request $request
     * @param $version
     * @return bool
     */
    private function isCorrectSign(Payment $payment, Request $request, $version): bool
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
                                JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
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
                }

                CEventLog::Add(
                    [
                        'SEVERITY' => 'INFO',
                        'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                        'MODULE_ID' => 'invoicebox.payment',
                        'DESCRIPTION' => json_encode(
                            [
                                'error' => Loc::getMessage(
                                    'SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_API_KEY_IN_REQUEST'
                                )
                            ],
                            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
                        ),
                    ]
                );

                return false;
                break;
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
                break;
        }
        return false;
    }

    /**
     * @param Payment $payment
     * @param float $amount
     * @return bool
     */
    private function isCorrectSum(Payment $payment, float $amount): bool
    {
        $sum = PriceMaths::roundByFormatCurrency($amount, $payment->getField('CURRENCY'));
        $paymentSum = PriceMaths::roundByFormatCurrency(
            $this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY'),
            $payment->getField('CURRENCY')
        );

        if ($paymentSum == $sum) {
            return true;
        }

        CEventLog::Add(
            [
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                'MODULE_ID' => 'invoicebox.payment',
                'DESCRIPTION' => json_encode(
                    [
                        'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_REQUEST_AMOUNT_IS_NOT_VALID')
                    ],
                    JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
                ),
            ]
        );

        return false;
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    private function isPaidByOther(Payment $payment): bool
    {
        if ($payment->isPaid() && $this->service->getField('PAY_SYSTEM_ID') !== $payment->getPaySystem()) {
            CEventLog::Add(
                [
                    'SEVERITY' => 'INFO',
                    'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
                    'MODULE_ID' => 'invoicebox.payment',
                    'DESCRIPTION' => json_encode(
                        [
                            'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_REQUEST_IS_OTHER_PAYMENT_PAYED')
                        ],
                        JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
                    ),
                ]
            );
            return true;
        }
        return false;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        if (!is_null($request->get('participantOrderId'))) {
            return $request->get('participantOrderId');
        }

        $inputStream = static::readFromStream();
        if ($inputStream && static::isJSON($inputStream)) {
            $data = static::decode($inputStream);
            if ($data === false) {
                return false;
            }

            return $data['merchantOrderId'];
        }

        return false;
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

        if ($this->isCorrectSign($payment, $request, $version)) {
            return $this->processNoticeAction($payment, $request, $version);
        }

        PaySystem\ErrorLog::add([
            'ACTION' => 'processRequest',
            'MESSAGE' => 'Incorrect sign'
        ]);
        $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['sign']));

        return $result;
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @param $version
     * @return PaySystem\ServiceResult
     */
    private function processNoticeAction(Payment $payment, Request $request, $version)
    {
        $result = new PaySystem\ServiceResult();
        $amount = 0;
        $orderCurrency = 'RUB';
        $invoiceId = "";
        $psStatusDescription = "";
        $currency = "";
        $bPay = false;

        switch ($version) {
            case self::PAYMENT_VERSION_2:
                $psStatusDescription = Loc::getMessage('SALE_HPS_INVOICEBOX_RES_NUMBER') . ": " . $request->get(
                        'participantOrderId'
                    ) . " (" . $request->get('ucode') . ")";
                $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_DATEPAY') . ": " . date(
                        "d.m.Y H:i:s"
                    );
                $psStatusDescription .= "; " . Loc::getMessage(
                        'SALE_HPS_INVOICEBOX_RES_PAY_TYPE'
                    ) . ": " . $request->get(
                        "agentName"
                    );
                $amount = $request->get('amount');
                $invoiceId = $request->get('ucode');
                $currency = $request->get('currency');
                $bPay = true;
                break;
            case self::PAYMENT_VERSION_3:
                $inputStream = static::readFromStream();
                $data = static::decode($inputStream);
                $psStatusDescription = Loc::getMessage(
                        'SALE_HPS_INVOICEBOX_RES_NUMBER'
                    ) . ": " . $data['merchantOrderId'] . " (" . $data['id'] . ")";
                $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_DATEPAY') . ": " . date(
                        "d.m.Y H:i:s"
                    );
                $amount = $data['amount'];
                $invoiceId = $data['id'];
                $currency = $data['currencyId'];
                $bPay = ($data['status'] === self::STATUS_COMPLETED);
                break;
        }

        // Check for test requests
        if ($invoiceId && in_array($invoiceId, self::TEST_ORDER)) {
            return $result;
        }

        // Check payment status
        if (!$bPay) {
            PaySystem\ErrorLog::add([
                'ACTION' => 'processNoticeAction',
                'MESSAGE' => 'Unknown order status'
            ]);
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['not_found']));
            return $result;
        }

        // Check amount
        if (!$this->isCorrectSum($payment, $amount)) {
            PaySystem\ErrorLog::add([
                'ACTION' => 'processNoticeAction',
                'MESSAGE' => 'Incorrect payment amount (' . $amount . ')'
            ]);
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['amount']));
            return $result;
        }

        // Check currency
        // ToDo: check order/payment currency
        if ($currency !== $orderCurrency) {
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['currency']));
            return $result;
        }

        // Check if order paid by other payment system
        if ($this->isPaidByOther($payment)) {
            $result->addError(new Error(self::NOTIFICATION_ERROR_CODE['paid']));
            return $result;
        }

        $fields = [
            "PS_STATUS" => "Y",
            "PS_STATUS_CODE" => "-",
            "PS_STATUS_DESCRIPTION" => $psStatusDescription,
            "PS_STATUS_MESSAGE" => Loc::getMessage('SALE_HPS_INVOICEBOX_RES_PAYED'),
            "PS_SUM" => $amount,
            "PS_CURRENCY" => $currency,
            "PS_RESPONSE_DATE" => new DateTime(),
        ];

        $result->setPsData($fields);
        $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);

        return $result;
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    protected function isTestMode(Payment $payment = null): bool
    {
        return ($this->getBusinessValue($payment, 'PS_IS_TEST') == 'Y');
    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['RUB'];
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
            Header("User-Agent: " . $this->getUserAgent());
            echo 'OK';
        }
    }

    /**
     * @return array
     */
    public static function getHandlerModeList()
    {
        return [
            static::PAYMENT_VERSION_2 => Loc::getMessage('SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOCOL_2'),
            static::PAYMENT_VERSION_3 => Loc::getMessage('SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOCOL_3'),
        ];
    }
}
