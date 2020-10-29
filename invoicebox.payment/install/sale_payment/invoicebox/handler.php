<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

class InvoiceBoxHandler extends PaySystem\ServiceHandler
{
    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return array
     */
    protected function getPreparedParams(Payment $payment, Request $request = null)
    {
        $signatureValue = md5(
            $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_ID') .
            $this->getBusinessValue($payment, 'PAYMENT_ID') .
            $this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY') .
            $this->getBusinessValue($payment, 'PAYMENT_CURRENCY') .
            $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY')
        ); //

        $extraParams = array();
        if (method_exists(parent, "getPreparedParams")) {
            $extraParams = parent::getPreparedParams($payment, $request);
        }; //

        $extraParams["URL"] = $this->getUrl($payment, 'pay');
        $extraParams["PS_MODE"] = $this->service->getField('PS_MODE');
        $extraParams["SIGNATURE_VALUE"] = $signatureValue;
        $extraParams["INVOICEBOX_SUCCESS_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_SUCCESS');
        $extraParams["INVOICEBOX_CANCEL_URL"] = $this->getBusinessValue($payment, 'INVOICEBOX_RETURN_URL_CANCEL');
        $extraParams["INVOICEBOX_URL_NOTIFY"] = "http://" . $_SERVER['SERVER_NAME'] . "/bitrix/tools/invoicebox/notification.php";
        $extraParams["INVOICEBOX_API_KEY"] = $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY');
        $extraParams["BX_PAYSYSTEM_CODE"] = $payment->getPaymentSystemId();
        $paymentCollection = $payment->getCollection();

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();

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
        /** @var \Bitrix\Sale\PaymentCollection $paymentCollection */
        $paymentCollection = $payment->getCollection();

        /** @var \Bitrix\Sale\Order $order */
        $order = $paymentCollection->getOrder();

        $extraParams = $this->getPreparedParams($payment, $request);
        $extraParams["BASKET_ITEMS"] = $order->getBasket();
        $extraParams["DELIVERY_PRICE"] = $order->getDeliveryPrice();
        $this->setExtraParams($extraParams);

        return $this->showTemplate($payment, "template");
    } //

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
        ); //
    } //

    /**
     * @param Request $request
     * @param $paySystemId
     * @return bool
     */
    static protected function isMyResponseExtended(Request $request, $paySystemId)
    {
        return true;
    } //

    /**
     * @param Payment $payment
     * @param $request
     * @return bool
     */
    private function isCorrectHash(Payment $payment, Request $request)
    {
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
            $this->getBusinessValue($payment, 'INVOICEBOX_PARTICIPANT_APIKEY');

        $sign_crcA = md5($sign_strA); //

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
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return bool
     */
    private function isCorrectSum(Payment $payment, Request $request)
    {
        $sum = PriceMaths::roundByFormatCurrency($request->get('amount'), $payment->getField('CURRENCY'));
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
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        return $request->get('participantOrderId');
    } //

    /**
     * @return mixed
     */
    protected function getUrlList()
    {
        return array(
            'pay' => array(
                self::ACTIVE_URL => 'https://go.invoicebox.ru/module_inbox_auto.u'
            )
        ); //
    } //

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {
        $result = new PaySystem\ServiceResult();

        if ($this->isCorrectHash($payment, $request)) {
            return $this->processNoticeAction($payment, $request);
        } else {
            PaySystem\ErrorLog::add(
                array(
                    'ACTION' => 'processRequest',
                    'MESSAGE' => 'Incorrect hash'
                )
            ); //
            $result->addError(new Error('Incorrect hash'));
        }

        return $result;
    } //

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    private function processNoticeAction(Payment $payment, Request $request)
    {
        $result = new PaySystem\ServiceResult();

        $psStatusDescription = Loc::getMessage('SALE_HPS_INVOICEBOX_RES_NUMBER') . ": " . $request->get(
                'participantOrderId'
            ) . " (" . $request->get('ucode') . ")";
        $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_DATEPAY') . ": " . date("d.m.Y H:i:s");
        $psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_INVOICEBOX_RES_PAY_TYPE') . ": " . $request->get(
                "agentName"
            );

        $fields = array(
            "PS_STATUS" => "Y",
            "PS_STATUS_CODE" => "-",
            "PS_STATUS_DESCRIPTION" => $psStatusDescription,
            "PS_STATUS_MESSAGE" => Loc::getMessage('SALE_HPS_INVOICEBOX_RES_PAYED'),
            "PS_SUM" => $request->get('amount'),
            "PS_CURRENCY" => $this->getBusinessValue($payment, "PAYMENT_CURRENCY"),
            "PS_RESPONSE_DATE" => new DateTime(),
        );

        $result->setPsData($fields);

        if ($this->isCorrectSum($payment, $request)) {
            $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
//			return $this->orderPayment($payment, $request);
        } else {
            PaySystem\ErrorLog::add(
                array(
                    'ACTION' => 'processNoticeAction',
                    'MESSAGE' => 'Incorrect sum'
                )
            );
            $result->addError(new Error('Incorrect payment amount'));
        }

        return $result;
    } //

    /**
     * @param Payment $payment
     * @return bool
     */
    protected function isTestMode(Payment $payment = null)
    {
        return ($this->getBusinessValue($payment, 'PS_IS_TEST') == 'Y');
    } //

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return array('RUB');
    } //

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
        }; //if
    } //

    /**
     * @return array
     */
    public static function getHandlerModeList()
    {
        return array(
            '' => Loc::getMessage('SALE_HPS_INVOICEBOX_NO_CHOOSE')
        ); //
    } //
}
