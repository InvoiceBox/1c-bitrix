<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (CModule::IncludeModule('sale')) {
    $arPaySys = CSaleStatus::GetList(['SORT' => 'ASC'], ['LID' => LANGUAGE_ID]);
    while ($arRes = $arPaySys->Fetch()) {
        $arListStatus[$arRes['ID']] = '[' . $arRes['ID'] . '] ' . $arRes['NAME'];
    }
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$host = $request->isHttps() ? 'https' : 'http';

$description = [
    'MAIN' => Loc::getMessage('SALE_HPS_INVOICEBOX_MAIN_DESCRIPTION'),
];

$arTypePosition = [
    'service' => Loc::getMessage('SALE_HPS_INVOICEBOX_TYPE_SERVICE'),
    'commodity' => Loc::getMessage('SALE_HPS_INVOICEBOX_TYPE_COMMODITY'),
];

$arTypePayment = [
    'full_prepayment' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_TYPE_FULL_PREPAYMENT'),
    'prepayment' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_TYPE_PREPAYMENT'),
    'advance' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_TYPE_ADVANCE'),
    'full_payment' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_TYPE_FULL_PAYMENT'),
];

$arTypeVAT = [
    'NONE' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT_NONE'),
    'RUS_VAT0' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT0'),
    'RUS_VAT10' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT10'),
    'RUS_VAT20' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT20'),
//    'RUS_VAT110' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT110'),
//    'RUS_VAT120' => Loc::getMessage('SALE_HPS_INVOICEBOX_RUS_VAT120'),
];

$arTypePerson = [
    'order' => Loc::getMessage('SALE_HPS_INVOICEBOX_BUYER_TYPE_ORDER'),
    'private' => Loc::getMessage('SALE_HPS_INVOICEBOX_BUYER_TYPE_PRIVATE'),
    'legal' => Loc::getMessage('SALE_HPS_INVOICEBOX_BUYER_TYPE_LEGAL'),
];

$data = [
    'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_TITLE'),
    'SORT' => 500,
    'CODES' => [
        'INVOICEBOX_PARTICIPANT_ID' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PARTICIPANT_ID'),
            'SORT' => 100,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_2',
        ],
        'INVOICEBOX_PARTICIPANT_IDENT' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PARTICIPANT_IDENT'),
            'SORT' => 150,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_2',
        ],
        'INVOICEBOX_PARTICIPANT_APIKEY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PARTICIPANT_APIKEY'),
            'SORT' => 200,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_2',
        ],
        "INVOICEBOX_RETURN_URL_NOTIFY_2" => [
            "NAME" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_NOTIFY"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_NOTIFY_DESC"),
            'SORT' => 300,
            'GROUP' => "CONNECT_SETTINGS_INVOICEBOX_2",
            'DEFAULT' => [
                'PROVIDER_KEY' => 'VALUE',
                'PROVIDER_VALUE' => $host . '://' . $request->getHttpHost(
                    ) . '/bitrix/tools/invoicebox/notification.php',
            ],
        ],
        'INVOICEBOX_AUTH_TOKEN' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_AUTH_TOKEN'),
            'SORT' => 200,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_3',
        ],
        'INVOICEBOX_NOTIFICATION_TOKEN_V3' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_NOTIFICATION_TOKEN'),
            'SORT' => 210,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_3',
        ],
        'INVOICEBOX_PARTICIPANT_ID_V3' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PARTICIPANT_ID_V3'),
            'SORT' => 200,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_3',
        ],
        'INVOICEBOX_TYPE_BASKET' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_TYPE_BASKET'),
            'SORT' => 1010,
            'GROUP' => 'GENERAL_SETTINGS',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'commodity',
                'OPTIONS' => $arTypePosition
            ],
        ],
        'INVOICEBOX_VAT_RATE_BASKET' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_INVOICEBOX_VAT_RATE_BASKET'),
            'DESCRIPTION' => Loc::getMessage("SALE_HPS_INVOICEBOX_INVOICEBOX_VAT_RATE_BASKET_DESC"),
            'SORT' => 1015,
            'GROUP' => 'GENERAL_SETTINGS',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'RUS_VAT20',
                'OPTIONS' => array_merge(
                    $arTypeVAT,
                    [
                        'SETTINGS_BASKET' => Loc::getMessage(
                            'SALE_HPS_INVOICEBOX_RUS_VAT_SETTINGS_BASKET'
                        )
                    ]
                )
            ],
        ],
        'INVOICEBOX_TYPE_DELIVERY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_TYPE_DELIVERY'),
            'SORT' => 1020,
            'GROUP' => 'GENERAL_SETTINGS',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'service',
                'OPTIONS' => $arTypePosition
            ],
        ],
        'INVOICEBOX_VAT_RATE_DELIVERY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_INVOICEBOX_VAT_RATE_DELIVERY'),
            'DESCRIPTION' => Loc::getMessage("SALE_HPS_INVOICEBOX_INVOICEBOX_VAT_RATE_DELIVERY_DESC"),
            'SORT' => 1025,
            'GROUP' => 'GENERAL_SETTINGS',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'RUS_VAT20',
                'OPTIONS' => array_merge(
                    $arTypeVAT,
                    [
                        'SETTINGS_DELIVERY' => Loc::getMessage(
                            'SALE_HPS_INVOICEBOX_RUS_VAT_SETTINGS_DELIVERY'
                        )
                    ]
                )
            ],
        ],
        'INVOICEBOX_PAYMENT_TYPE' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_TYPE'),
            'SORT' => 500,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_3',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'full_prepayment',
                'OPTIONS' => $arTypePayment
            ],
        ],
        "INVOICEBOX_RETURN_URL_NOTIFY_3" => [
            "NAME" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_NOTIFY"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_NOTIFY_DESC"),
            'SORT' => 600,
            'GROUP' => "CONNECT_SETTINGS_INVOICEBOX_3",
            'DEFAULT' => [
                'PROVIDER_KEY' => 'VALUE',
                'PROVIDER_VALUE' => $host . '://' . $request->getHttpHost(
                    ) . '/bitrix/tools/invoicebox/notification_v3.php',
            ],
        ],
        'INVOICEBOX_MEASURE_DEFAULT' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_MEASURE_DEFAULT'),
            'SORT' => 100,
            'GROUP' => 'PAYMENT',
        ],
        'INVOICEBOX_ORDERDESCR' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_ORDERDESCR'),
            'SORT' => 400,
            'GROUP' => 'PAYMENT',
        ],
        'PAYMENT_ID' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PAYMENT_ID'),
            'SORT' => 700,
            'GROUP' => 'PAYMENT',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'ID',
                'PROVIDER_KEY' => 'PAYMENT'
            ]
        ],
        'PAYMENT_SHOULD_PAY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_SHOULD_PAY'),
            'SORT' => 800,
            'GROUP' => 'PAYMENT',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'SUM',
                'PROVIDER_KEY' => 'PAYMENT'
            ]
        ],
        'PAYMENT_CURRENCY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_CURRENCY'),
            'SORT' => 900,
            'GROUP' => 'PAYMENT',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'CURRENCY',
                'PROVIDER_KEY' => 'PAYMENT'
            ]
        ],
        'PAYMENT_DATE_INSERT' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_DATE_INSERT'),
            'SORT' => 1000,
            'GROUP' => 'PAYMENT',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'DATE_BILL',
                'PROVIDER_KEY' => 'PAYMENT'
            ]
        ],
        'BUYER_TYPE' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_BUYER_TYPE'),
            'SORT' => 1005,
            'GROUP' => 'BUYER_PERSON',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'VALUE' => 'private',
                'OPTIONS' => $arTypePerson
            ],
        ],
        'BUYER_PERSON_NAME' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_NAME'),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_NAME_DESC"),
            'SORT' => 1010,
            'GROUP' => 'BUYER_PERSON',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'LAST_NAME',
                'PROVIDER_KEY' => 'USER',
            ],
        ],
        'BUYER_PERSON_EMAIL' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_EMAIL_USER'),
            'SORT' => 1100,
            'GROUP' => 'BUYER_PERSON',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'EMAIL',
                'PROVIDER_KEY' => 'PROPERTY'
            ]
        ],
        'BUYER_PERSON_PHONE' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_PHONE'),
            'SORT' => 1030,
            'GROUP' => 'BUYER_PERSON',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'PERSONAL_MOBILE',
                'PROVIDER_KEY' => 'USER',
            ],
        ],
        'BUYER_PERSON_INN' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_INN'),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_INN_DESC"),
            'GROUP' => 'BUYER_PERSON',
            'SORT' => 1040,
        ],
        'BUYER_PERSON_REGISTR_ADDRESS' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_REGISTR_ADDRESS'),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_REGISTR_ADDRESS_DESC"),
            'GROUP' => 'BUYER_PERSON',
            'SORT' => 1050,
        ],
        'PS_CHANGE_STATUS_PAY' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_CHANGE_STATUS_PAY'),
            'SORT' => 1200,
            'GROUP' => 'GENERAL_SETTINGS',
            "INPUT" => [
                'TYPE' => 'Y/N'
            ]
        ],
        'PS_IS_TEST' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_TESTMODE'),
            'SORT' => 1300,
            'GROUP' => 'CONNECT_SETTINGS_INVOICEBOX_2',
            "INPUT" => [
                'TYPE' => 'Y/N'
            ]
        ],
        'PS_IS_DEFFERED_PAYMENT' => [
            'NAME' => Loc::getMessage('SALE_HPS_INVOICEBOX_DEFFERED_PAYMENT'),
            'SORT' => 1400,
            'GROUP' => 'PAYMENT',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'OPTIONS' => $arListStatus
            ]
        ],
        /*'PS_STATUS_ORDER_AFTER_PAY' => [
            'NAME' 	=> Loc::getMessage('SALE_HPS_INVOICEBOX_STATUS_ORDER_AFTER_PAY'),
            'SORT' 	=> 1500,
            'GROUP' => 'PAYMENT',
            "INPUT" => [
                'TYPE' => 'ENUM',
                'OPTIONS' =>$arListStatus
            ]
        ],*/
        "INVOICEBOX_RETURN_URL" => [
            "NAME" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL"),
            'SORT' => 1400,
            'GROUP' => "GENERAL_SETTINGS",
            'DEFAULT' => [
                'PROVIDER_KEY' => 'VALUE',
                'PROVIDER_VALUE' => $host . '://' . $request->getHttpHost(),
            ],
        ],
        "INVOICEBOX_RETURN_URL_SUCCESS" => [
            "NAME" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_SUCCESS"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_SUCCESS_DESC"),
            'SORT' => 1410,
            'GROUP' => "GENERAL_SETTINGS",
            'DEFAULT' => [
                'PROVIDER_KEY' => 'VALUE',
                'PROVIDER_VALUE' => $host . '://' . $request->getHttpHost(
                    ) . '/personal/order/payment/invoicebox/success.php',
            ],
        ],
        "INVOICEBOX_RETURN_URL_CANCEL" => [
            "NAME" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_CANCEL"),
            "DESCRIPTION" => Loc::getMessage("SALE_HPS_INVOICEBOX_RETURN_URL_CANCEL_DESC"),
            'SORT' => 1420,
            'GROUP' => "GENERAL_SETTINGS",
            'DEFAULT' => [
                'PROVIDER_KEY' => 'VALUE',
                'PROVIDER_VALUE' => $host . '://' . $request->getHttpHost(
                    ) . '/personal/order/payment/invoicebox/failed.php',
            ],
        ],
    ]
]; //
