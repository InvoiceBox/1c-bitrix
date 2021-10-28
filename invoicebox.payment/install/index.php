<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (class_exists("invoicebox_payment")) {
    return;
}

class invoicebox_payment extends CModule
{
    const IB_OBJECT_TYPE = 'IB_OBJECT_TYPE';
    const MODULE_ID = 'invoicebox.payment';
    var $MODULE_ID = 'invoicebox.payment';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $strError = '';

    function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage('SALE_HPS_INVOICEBOX_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SALE_HPS_INVOICEBOX_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = "Invoicebox";
        $this->PARTNER_URI = "https://www.invoicebox.ru";
    }

    function InstallEvents()
    {
        RegisterModuleDependences(
            'sale',
            'OnGetBusinessValueGroups',
            'invoicebox.payment',
            'CInvoicebox',
            'getBusValueGroups'
        );
        return true;
    }

    function UnInstallEvents()
    {
        UnRegisterModuleDependences(
            'sale',
            'OnGetBusinessValueGroups',
            'invoicebox.payment',
            'CInvoicebox',
            'getBusValueGroups'
        );
        return true;
    }

    function InstallDB()
    {
        if (!Loader::includeModule('sale')) {
            return false;
        }

        if (!Loader::includeModule('catalog')) {
            return false;
        }

        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $enumList = [
            "commodity" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_1'),
            "service" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_4')
        ]; //

        $rsIBlockList = GetIBlockList("catalog");
        while ($arIBlock = $rsIBlockList->GetNext()) {
            $property = \CIBlockProperty::GetList(
                array("sort" => "asc", "name" => "asc"),
                array("ACTIVE" => "Y", "CODE" => self::IB_OBJECT_TYPE, "PROPERTY_TYPE" => "L")
            ); //
            if (!$property->SelectedRowsCount()) {
                $arFields = array(
                    "NAME" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE'),
                    "HINT" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_HINT'),
                    "ACTIVE" => "Y",
                    "IS_REQUIRED" => "Y",
                    "SORT" => "600",
                    "CODE" => self::IB_OBJECT_TYPE,
                    "PROPERTY_TYPE" => "L",
                    "IBLOCK_ID" => $arIBlock["ID"]
                );
                $ibp = new \CIBlockProperty;
                $propetryId = $ibp->Add($arFields);
            } else {
                $tmp = $property->GetNext();
                $propetryId = $tmp["ID"];
            };

            $ibpenum = new \CIBlockPropertyEnum;
            foreach ($enumList as $_xmlId => $_name) {
                $enum = $ibpenum->GetList(
                    array("sort" => "asc", "name" => "asc"),
                    array("ID" => $propetryId, "XML_ID" => $_xmlId)
                ); //
                if (!$enum->SelectedRowsCount()) {
                    $enumId = $ibpenum->Add(
                        array(
                            "IBLOCK_ID" => $arIBlock["ID"],
                            "PROPERTY_ID" => $propetryId,
                            "VALUE" => $_name,
                            "XML_ID" => $_xmlId
                        )
                    );
                }; //
            }; //enumList
        }; //arIBlock

        return true;
    }

    function UnInstallDB()
    {
        return true;
    }

    function InstallFiles($arParams = array(), $alternativePath = false)
    {
        global $APPLICATION;
        $countErr = 0;
        $pathMod = '/';

        if (strpos(__DIR__, 'local') !== false) {
            $pathMod = '/local/';
        } elseif (strpos(__DIR__, 'bitrix') !== false) {
            $pathMod = '/bitrix/';
        }

        if (!CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . $pathMod . 'modules/' . self::MODULE_ID . '/install/notifications/invoicebox',
            $_SERVER["DOCUMENT_ROOT"] . '/personal/order/payment/invoicebox',
            true,
            true
        )) {
            $countErr++;
        }

        if (!CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . $pathMod . 'modules/' . self::MODULE_ID . '/install/tools/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/',
            true,
            true
        )) {
            $countErr++;
        }

        if (!CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . $pathMod . 'modules/' . self::MODULE_ID . '/install/sale_payment/invoicebox/',
            $_SERVER["DOCUMENT_ROOT"] . $pathMod . 'php_interface/include/sale_payment/invoicebox/',
            true,
            true
        )) {
            $countErr++;
        }

        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . $pathMod . 'modules/' . self::MODULE_ID . '/install/sale_payment/invoicebox/images/',
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/images/sale/sale_payments/",
            true,
            true
        );

        $this->InstallDB();

        if ($countErr > 0) {
            $this->UnInstallFiles();
            $this->UnInstallDB();
            return false;
        }
        return true;
    }

    function UnInstallFiles()
    {
        $pathMod = '/';

        if (strpos(__DIR__, 'local') !== false) {
            $pathMod = '/local/';
        } elseif (strpos(__DIR__, 'bitrix') !== false) {
            $pathMod = '/bitrix/';
        }

        DeleteDirFilesEx($pathMod . "php_interface/include/sale_payment/invoicebox");
        DeleteDirFilesEx("/personal/order/payment/invoicebox");
        DeleteDirFilesEx("/bitrix/tools/invoicebox/");

        if (is_dir($p = $_SERVER['DOCUMENT_ROOT'] . $pathMod . 'modules/mibok.pay/install/components')) {
            if ($dir = opendir($p)) {
                while (false !== $item = readdir($dir)) {
                    if ($item == '..' || $item == '.' || !is_dir($p0 = $p . '/' . $item)) {
                        continue;
                    }

                    $dir0 = opendir($p0);
                    while (false !== $item0 = readdir($dir0)) {
                        if ($item0 == '..' || $item0 == '.') {
                            continue;
                        }

                        if (is_dir($_SERVER['DOCUMENT_ROOT'] . $pathMod . 'components/' . $item . '/' . $item0)) {
                            DeleteDirFilesEx($pathMod . 'components/' . $item . '/' . $item0);
                        }
                    }
                    closedir($dir0);
                }
                closedir($dir);
            }
        }

        return true;
    }

    function DoInstall()
    {
        global $APPLICATION;
        if ($this->InstallFiles()) {
            RegisterModule(self::MODULE_ID);
        } else {
            $APPLICATION->throwException($this->strError);
        };
    }

    function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule(self::MODULE_ID);
        $this->UnInstallFiles();
    }
}
