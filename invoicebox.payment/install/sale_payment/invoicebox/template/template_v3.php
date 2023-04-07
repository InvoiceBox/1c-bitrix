<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;

Asset::getInstance()->addCss("/bitrix/themes/.default/sale.css");
Asset::getInstance()->addCss("/bitrix/php_interface/include/sale_payment/invoicebox/template/style.css");
Loc::loadMessages(__FILE__);

$params = isset($params) ? $params : [];

?>

<div class="sale-paysystem-wrapper">
	<span class="tablebodytext">
		<?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_TITLE") ?><br>
        <?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_ORDER") ?> <?= htmlspecialcharsbx(
            $params['PAYMENT_ID'] . Loc::getMessage("SALE_HPS_INVOICEBOX_FROM") . $params["PAYMENT_DATE_INSERT"]
        ) ?><br>
        <?= Loc::getMessage("SALE_HPS_INVOICEBOX_TEMPL_TO_PAY") ?> <b><?= SaleFormatCurrency(
                $params['PAYMENT_SHOULD_PAY'],
                $params["PAYMENT_CURRENCY"]
            ) ?></b>
	</span>
    <div class="sale-paysystem-invoicebox-button-container">
		<span class="sale-paysystem-invoicebox-button">
			<a class="sale-paysystem-invoicebox-button-item sale-paysystem-invoicebox-checkout-button-item"
               href="<?= $params['URL'] ?>" style="display: inline-block;">
				<?= Loc::getMessage('SALE_HPS_INVOICEBOX_TEMPL_BUTTON') ?>
			</a>
		</span>
        <span class="sale-paysystem-invoicebox-button-descrition"><?= Loc::getMessage(
                'SALE_HPS_INVOICEBOX_TEMPL_TO_PAY_REDIRECT_MESS'
            ) ?></span>
    </div>

    <p>
		<span class="tablebodytext sale-paysystem-description">
			<?= Loc::getMessage('SALE_HPS_INVOICEBOX_TEMPL_WARN') ?>
		</span>
    </p>
</div>
