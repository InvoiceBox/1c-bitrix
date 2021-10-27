<?php

$MESS['SALE_HPS_INVOICEBOX_RES_NUMBER'] = 'Account number in the store';
$MESS['SALE_HPS_INVOICEBOX_RES_DATEPAY'] = 'payment date';
$MESS['SALE_HPS_INVOICEBOX_RES_PAYED'] = 'Paid';
$MESS['SALE_HPS_INVOICEBOX_RES_PAY_TYPE'] = 'Payment method';
$MESS["SALE_HPS_INVOICEBOX_NO_CHOOSE"] = "Not selected";

$MESS['SALE_HPS_INVOICEBOX_LOG_FORBIDDEN_SERVER'] = 'request from an failed server';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_CONF'] = 'invoicebox module is not configured in the system';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_NOT_SET_API_KEY'] = 'api_key is not set in the invoicebox module';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_REQUEST_TYPE'] = 'invalid request type';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_ERROR_API_KEY_IN_REQUEST'] = 'invalid key in request';
$MESS['SALE_HPS_INVOICEBOX_LOG_MODULE_IS_TEST_SUCCESS'] = 'test request completed successfully';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_PARTICIPANT_IS_NOT_VALID'] = 'wrong store id';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_ORDER_IS_NOT_VALID'] = 'request for non-existent order';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_AMOUNT_IS_NOT_VALID'] = 'the payment amount in the order does not match';
$MESS['SALE_HPS_INVOICEBOX_LOG_ORDER_IS_PAY'] = 'order successfully paid';
$MESS['SALE_HPS_INVOICEBOX_LOG_REQUEST_IS_OTHER_PAYMENT_PAYED'] = 'order was paid for by another payment system';

$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL"] = "Protocol version";
$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_2"] = "Invoicebox v2";
$MESS["SALE_HPS_INVOICEBOX_PS_CHANGE_VERSION_PROTOKOL_3"] = "Invoicebox v3";
$MESS["MEASURE_NAME_DEFAULT"] = "шт.";
$MESS["MEASURE_CODE_DEFAULT"] = 796;

$MESS["INVOICEBOX_CODE_ERROR_unauthorized"] = 'The request is not authorized. Perhaps, the request did not specify or indicate incorrect authorization data.';
$MESS["INVOICEBOX_CODE_ERROR_forbidden"] = 'Access to the resource or object is denied.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_expiration_date"] = 'Invalid order expiration date, check the expirationDate field value.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_currency"] = 'Wrong value of the order currency, check the value of the currencyId field.';
$MESS["INVOICEBOX_CODE_ERROR_language_not_found"] = 'Wrong value of the order language, check the value of the languageId field.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_total_amount"] = 'Error in calculating the total cost of one of the order items, check the correctness of the calculation of the totalAmount field in BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_total_vat_amount"] = 'Error in calculating the total VAT amount for one of the order items, check the correctness of the calculation of the totalVatAmount field in BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_basket_item_amount_wo_vat"] = 'Error in calculating the total value of one of the order items excluding VAT, check the correctness of the calculation of the amountWoVat field of the BasketItem.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_total_amount"] = 'Error in calculating the total amount of the order, check the correctness of calculating the amount.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_total_vat_amount"] = 'Error in calculating the total amount of VAT on the order, check the correctness of the calculation of vatAmount.';
$MESS["INVOICEBOX_CODE_ERROR_wrong_order_status"] = 'Invalid order status.';
$MESS["INVOICEBOX_ERROR"] = 'Payment error';

$MESS["INVOICEBOX_STATUS_created"] = 'Payment status: new';
$MESS["INVOICEBOX_STATUS_expired"] = 'Payment error: payment date is late';
$MESS["INVOICEBOX_STATUS_processing"] = 'Expect payment processing';
$MESS["INVOICEBOX_STATUS_processing-error"] = 'Payment error: processing problem';
$MESS["INVOICEBOX_STATUS_completed"] = 'Payment status: completed';
$MESS["INVOICEBOX_STATUS_canceled"] = 'Payment status: canceled';

$MESS["INVOICEBOX_WITHOUT_VAT"] = 'without VAT';
