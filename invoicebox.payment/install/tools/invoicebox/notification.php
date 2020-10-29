<?php

define("STOP_STATISTICS", true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
define("DisableEventsCheck", true);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\CModule::IncludeModule('sale');

use \Bitrix\Main\Application;
use \Bitrix\Sale\PaySystem;
use Bitrix\Main\Localization\Loc;

global $APPLICATION;
Loc::loadMessages(__FILE__);

//получаем объект запроса и проверяем наличие платежной системы
$context = Application::getInstance()->getContext();
$request = $context->getRequest();

//проверка ip-адресов и типа запроса
$legal_ip = [
    '77.244.212.7',
    '77.244.212.8',
    '77.244.212.9'
];

if ($request->get('testMode') == 1 && !in_array($_SERVER['REMOTE_ADDR'], $legal_ip)) {
    CEventLog::Add(
        [
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
            'MODULE_ID' => 'invoicebox.payment',
            'DESCRIPTION' => json_encode(
                [
                    'success' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_TEST_SUCCESS')
                ],
                true
            ),
        ]
    );
    exit('success: checking test mode');
}

if (!in_array($_SERVER['REMOTE_ADDR'], $legal_ip)) {
    CEventLog::Add(
        [
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
            'MODULE_ID' => 'invoicebox.payment',
            'DESCRIPTION' => json_encode(
                [
                    'ip_server' => $_SERVER['REMOTE_ADDR'],
                    'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_FORBIDDEN_SERVER')
                ],
                true
            ),
        ]
    );
    exit('error: 403 forbidden');
}
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    CEventLog::Add(
        [
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
            'MODULE_ID' => 'invoicebox.payment',
            'DESCRIPTION' => json_encode(
                [
                    'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_REQUEST_TYPE')
                ],
                true
            ),
        ]
    );
    exit ('error: request type is invalid');
}


$item = PaySystem\Manager::searchByRequest($request);
if ($item !== false) {
    $service = new PaySystem\Service($item);

    if ($service instanceof PaySystem\Service) {
        $res = $service->processRequest($request);
        $GLOBALS['APPLICATION']->RestartBuffer();
        if (count($res->getErrorMessages()) > 0) {
            exit('error: processRequest (' . implode(", ", $res->getErrorMessages()) . ')');
        } else {
            exit('OK');
        }
    }
} else {
    CEventLog::Add(
        [
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
            'MODULE_ID' => 'invoicebox.payment',
            'DESCRIPTION' => json_encode(
                [
                    'error' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_CONF')
                ],
                true
            ),
        ]
    );
    exit('error: module is not configured');
}
