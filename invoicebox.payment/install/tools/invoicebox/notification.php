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

// Получаем объект запроса и проверяем наличие платежной системы
$context = Application::getInstance()->getContext();
$request = $context->getRequest();

if ($request->get('testMode') == 1) {
    CEventLog::Add(
        [
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => 'INVOICE_PAYMENT_LOG',
            'MODULE_ID' => 'invoicebox.payment',
            'DESCRIPTION' => json_encode(
                [
                    'success' => Loc::getMessage('SALE_HPS_INVOICEBOX_LOG_MODULE_IS_TEST_SUCCESS')
                ],
                JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
            ),
        ]
    );
    exit('success: checking test mode');
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
                JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
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
                JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
            ),
        ]
    );
    exit('error: module is not configured');
}
