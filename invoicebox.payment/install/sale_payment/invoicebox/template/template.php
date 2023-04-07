<?php

use Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Page\Asset;

Asset::getInstance()->addCss("/bitrix/themes/.default/sale.css");
Asset::getInstance()->addCss("/bitrix/php_interface/include/sale_payment/invoicebox/template/style.css");

Loc::loadMessages(__FILE__);
$params = isset($params) ? $params : [];

?>

<form action="<?= $params['URL'] ?>" method="post" target="_blank" name="invoicebox_form">
    <input type="hidden" name="itransfer_encoding" value="utf-8"/>
    <input type="hidden" name="itransfer_participant_id"
           value="<?= htmlspecialcharsbx($params['INVOICEBOX_PARTICIPANT_ID']) ?>"/>
    <input type="hidden" name="itransfer_participant_ident"
           value="<?= htmlspecialcharsbx($params['INVOICEBOX_PARTICIPANT_IDENT']) ?>"/>
    <input type="hidden" name="itransfer_participant_sign" value="<?= $params['SIGNATURE_VALUE'] ?>"/>
    <input type="hidden" name="itransfer_cms_name" value="<?= $params['USER_AGENT'] ?>"/>
    <input type="hidden" name="itransfer_order_id" value="<?= htmlspecialcharsbx($params['PAYMENT_ID']) ?>"/>
    <input type="hidden" name="itransfer_order_amount"
           value="<?= htmlspecialcharsbx(number_format($params['PAYMENT_SHOULD_PAY'], 2, '.', '')) ?>"/>
    <input type="hidden" name="itransfer_order_quantity" value="1"/>
    <input type="hidden" name="itransfer_order_currency_ident"
           value="<?= htmlspecialcharsbx($params["PAYMENT_CURRENCY"]) ?>"/>
    <input type="hidden" name="itransfer_order_description"
           value="<?= htmlspecialcharsbx($params['INVOICEBOX_ORDERDESCR'] . " (#" . $params['ORDERID'] . ")") ?>"/>
    <input type="hidden" name="itransfer_body_type" value="<?= htmlspecialcharsbx($params['ORDER_PERSONAL_TYPE']) ?>" data-id="<?= htmlspecialcharsbx($params['ORDER_PERSONAL_TYPE_ID']) ?>" />
    <input type="hidden" name="itransfer_person_name" value="<?= htmlspecialcharsbx($params["BUYER_PERSON_NAME"]) ?>"/>
    <input type="hidden" name="itransfer_organization_tin" value="<?= htmlspecialcharsbx($params["BUYER_PERSON_INN"]) ?>"/>
    <input type="hidden" name="itransfer_organization_yaddress" value="<?= htmlspecialcharsbx($params["BUYER_PERSON_ADDRESS"]) ?>"/>
    <input type="hidden" name="itransfer_person_email"
           value="<?= htmlspecialcharsbx($params['BUYER_PERSON_EMAIL']) ?>"/>
    <input type="hidden" name="itransfer_person_phone"
           value="<?= htmlspecialcharsbx($params["BUYER_PERSON_PHONE"]) ?>"/>
    <input type="hidden" name="itransfer_url_returnsuccess"
           value="<?= htmlspecialcharsbx($params["INVOICEBOX_SUCCESS_URL"]) ?>"/>
    <input type="hidden" name="itransfer_url_cancel"
           value="<?= htmlspecialcharsbx($params["INVOICEBOX_CANCEL_URL"]) ?>"/>
    <input type="hidden" name="itransfer_url_notify"
           value="<?= htmlspecialcharsbx($params["INVOICEBOX_URL_NOTIFY"]) ?>"/>
    <?php if ($params['PS_IS_TEST'] == 'Y'): ?>
        <input type="hidden" name="itransfer_testmode" value="1"/>
    <?php endif; ?>
    <?php

    if ($params['BASKET_ITEMS_PREPARED']) {
        $itemNo = 0;
        foreach ($params['BASKET_ITEMS_PREPARED'] as $basketItem) {
            $itemNo++;
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_type" value="' . $basketItem["type"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_ident" value="' . $basketItem["sku"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_name" value="' . str_replace( '"', '/', $basketItem["name"] ) . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_quantity" value="' . $basketItem["quantity"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_measure" value="' . $basketItem["measure"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_measure_code" value="' . $basketItem["measureCode"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_price" value="' . $basketItem["amount"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_vat" value="' . $basketItem["totalVatAmount"] . '" />' . "\n";
            echo '<input type="hidden" name="itransfer_item' . $itemNo . '_vatrate" value="' . $basketItem["vatRate"] . '" />' . "\n";
        } //foreach
    } //

    ?>

    <div class="sale-paysystem-wrapper">
        <span class="tablebodytext">
            <?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_TITLE") ?><br>
            <?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_ORDER") ?> <?= htmlspecialcharsbx(
                $params['PAYMENT_ID'] . "  " . $params["PAYMENT_DATE_INSERT"]
            ) ?><br>
            <?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_TO_PAY") ?> <b><?= SaleFormatCurrency(
                    $params['PAYMENT_SHOULD_PAY'],
                    $params["PAYMENT_CURRENCY"]
                ) ?></b>
        </span>
        <div class="sale-paysystem-invoicebox-button-container">
            <input type="hidden" name="FinalStep" value="1">
            <?php if ($params['SUCCESS_PAY']) : ?>
                <span class="sale-paysystem-invoicebox-button">
                <input class='sale-paysystem-invoicebox-button-item' type="submit" name="Submit"
                       value="<?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_BUTTON") ?>">
            </span>
                <span class="sale-paysystem-invoicebox-button-descrition"><?= Loc::getMessage(
                        'SALE_HPS_INVOICEBOX_TEMPL_TO_PAY_REDIRECT_MESS'
                    ) ?></span>
            <?php else : ?>
                <span style="color:red"><?= Loc::getMessage("SALE_HPS_INVOICEBOX_PAY_INFO") ?></span>
            <?php endif; ?>
        </div>
        <p>
            <span class="tablebodytext sale-paysystem-description">
                <?= Loc::getMessage('SALE_HPS_INVOICEBOX_TEMPL_WARN') ?>
            </span>
        </p>
    </div>
</form>
