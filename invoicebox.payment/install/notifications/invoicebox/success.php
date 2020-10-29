<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/bx_root.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

echo Loc::getMessage('SALE_HPS_INVOICEBOX_SUCCESS');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
