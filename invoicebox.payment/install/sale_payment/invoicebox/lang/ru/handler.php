<?php

$MESS['SALE_HPS_INVOICEBOX_RES_NUMBER'] = 'Номер счёта в магазине';
$MESS['SALE_HPS_INVOICEBOX_RES_DATEPAY'] = 'Дата платежа';
$MESS['SALE_HPS_INVOICEBOX_RES_PAYED'] = 'Оплачен';
$MESS['SALE_HPS_INVOICEBOX_RES_PAY_TYPE'] = 'Способ оплаты';
$MESS["SALE_HPS_INVOICEBOX_NO_CHOOSE"] = "Не выбран";

$MESS['SALE_HPS_INVOICEBOX_LOG_FORBIDDEN_SERVER'] = 'обращение от неустановренного сервера';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_CONF'] = 'не настроен модуль invoicebox в системе';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_SET_API_KEY'] = 'не задан api_key в модуле invoicebox';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_REQUEST_TYPE'] = 'неверный тип запроса invoicebox';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_API_KEY_IN_REQUEST'] = 'неверный ключ в запросе invoicebox';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_TEST_SUCCESS'] = 'тестовый запрос успешно выполнен';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_PARTICIPANT_IS_NOT_VALID'] = 'неверный идентификатор магазина';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_ORDER_IS_NOT_VALID'] = 'запрос на несуществующий заказ';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_AMOUNT_IS_NOT_VALID'] = 'несоответствует сумма оплаты в заказе';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_IS_OTHER_PAYMENT_PAYED'] = 'заказ был оплачен другой платежной системой';
$MESS['SALE_HPS_INVOICEBOX_LOG_ORDER_IS_PAY'] = 'заказ успешно оплачен';

$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL"] = "Версия протокола";
$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_2"] = "Инвойсбокс v2";
$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_3"] = "Инвойсбокс v3";
$MESS["MEASURE_NAME_DEFAULT"] = "шт.";
$MESS["MEASURE_CODE_DEFAULT"] = 796;

$MESS["INVOICEBOX_CODE_ERROR_unauthorized"] = 'Запрос не авторизован. Возможно, в запросе не указаны или указаны неверные авторизационные данные.';
$MESS["INVOICEBOX_CODE_ERROR_forbidden"] = 'Доступ к ресурсу или объекту запрещен.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_expiration_date"] = 'Ошибочное значение срока действия заказа, проверьте значение поля expirationDate.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_currency"] = 'Ошибочное значение валюты заказа, проверьте значение поля currencyId.';
$MESS["INVOICEBOX_CODE_ERROR_language_not_found"] = 'Ошибочное значение языка заказа, проверьте значение поля languageId.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_total_amount"] = 'Ошибка подсчёта итоговой стоимости одной из позиций заказа, проверьте правильность расчёта поля totalAmount у BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_total_vat_amount"] = 'Ошибка подсчёта итоговой суммы НДС одной из позиций заказа, проверьте правильность расчёта поля totalVatAmount у BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_amount_wo_vat"] = 'Ошибка подсчёта итоговой стоимости одной из позиций заказа без НДС, проверьте правильность расчёта поля amountWoVat у BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_total_amount"] = 'Ошибка подсчёта итоговой суммы заказа, проверьте правильность подсчёта amount.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_total_vat_amount"] = 'Ошибка подсчёта итоговой суммы НДС заказа, проверьте правильность подсчёта vatAmount.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_order_status"] = 'Неверный статус заказа.';
$MESS["INVOICEBOX_ERROR"] = 'Ошибка оплаты';

$MESS["INVOICEBOX_STATUS_created"] = 'Статус платежа: новый';
$MESS["INVOICEBOX_STATUS_expired"] = 'Ошибка оплаты: дата платежа просрочена';
$MESS["INVOICEBOX_STATUS_processing"] = 'Ожидайте, платеж в обработке';
$MESS["INVOICEBOX_STATUS_processing-error"] = 'Ошибка оплаты: проблема с обработкой';
$MESS["INVOICEBOX_STATUS_completed"] = 'Статус платежа: завершен';
$MESS["INVOICEBOX_STATUS_canceled"] = 'Статус платежа: отменен';

$MESS["INVOICEBOX_WITHOUT_NDS"] = 'без ндс';
