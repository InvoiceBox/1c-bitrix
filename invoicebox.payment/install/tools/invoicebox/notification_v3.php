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
use \Bitrix\Main\Web\Json;

global $APPLICATION;
Loc::loadMessages(__FILE__);

$arAnswer = ['status' => 'error', 'code' => 'out_of_service'];

if (CModule::IncludeModule("sale"))
{
    $context = Application::getInstance()->getContext();
    $request = $context->getRequest();
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['merchantOrderId']) && \Bitrix\Sale\Order::getList(['filter' => ['ID' => $data['merchantOrderId']]])->fetch()) {
        $item = PaySystem\Manager::searchByRequest($request);
        
        if ($item !== false)
        {
            $service = new PaySystem\Service($item);

            if ($service instanceof PaySystem\Service)
            {
                $result = $service->processRequest($request);
                if (!$result->isSuccess())
                {
                    $arError = $result->getErrorMessages();
                    if (count($arError) > 0) {
                        $arAnswer['code'] = $arError[0];
                    }
                } else {
                    $arAnswer = ['status' => 'success'];
                }
            }
        }
        else
        {
            $debugInfo = implode("\n", $request->toArray());
            PaySystem\Logger::addDebugInfo('Pay system not found. Request: '.$debugInfo);
        }
    } else {
        $arAnswer['code'] = 'order_not_found';
    }
}

$APPLICATION->RestartBuffer();
$APPLICATION->FinalActions(json_encode($arAnswer));
die();