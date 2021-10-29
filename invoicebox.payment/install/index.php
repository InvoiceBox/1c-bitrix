<?php

use Bitrix\Main\Localization\Loc,
    Bitrix\Iblock,
    Bitrix\Iblock\IblockTable;

Loc::loadMessages(__FILE__);

if (class_exists("invoicebox_payment")) {
    return;
}

class invoicebox_payment extends CModule
{
    const IB_OBJECT_TYPE = 'IB_OBJECT_TYPE';
    const MODULE_ID = 'invoicebox.payment';
    const MODULE_LCHARSET = 'windows-1251';
    public $MODULE_ID = 'invoicebox.payment';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = "N";
    public $strError = '';
    public $errors = false;

    function __construct()
    {
        global $APPLICATION;
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage('SALE_HPS_INVOICEBOX_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SALE_HPS_INVOICEBOX_MODULE_DESCRIPTION');
        if (self::MODULE_LCHARSET !== 'utf-8') {
            $this->MODULE_DESCRIPTION = $APPLICATION->ConvertCharset(
                $this->MODULE_DESCRIPTION,
                self::MODULE_LCHARSET,
                "utf-8"
            );
            $this->MODULE_NAME = $APPLICATION->ConvertCharset($this->MODULE_NAME, self::MODULE_LCHARSET, "utf-8");
        } //
        $this->PARTNER_NAME = "Invoicebox";
        $this->PARTNER_URI = "https://www.invoicebox.ru";
    }

    /**
     * Install events
     * @return boolean
     */
    public function InstallEvents(): bool
    {
        RegisterModuleDependences(
            'iblock',
            'catalog',
            'sale',
            'OnGetBusinessValueGroups',
            'invoicebox.payment',
            'CInvoicebox',
            'getBusValueGroups'
        );
        return true;
    }

    /**
     * Uninstall events
     * @return boolean
     */
    public function UnInstallEvents(): bool
    {
        UnRegisterModuleDependences(
            'iblock',
            'catalog',
            'sale',
            'OnGetBusinessValueGroups',
            'invoicebox.payment',
            'CInvoicebox',
            'getBusValueGroups'
        );
        return true;
    }

    /**
     * Install DB
     * @return boolean
     */
    public function InstallDB(): bool
    {
        global $APPLICATION, $DB;

        CModule::includeModule("iblock");

        $enumList = [
            "commodity" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_1'),
            "service" => Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_4')
        ]; //

        $rsIBlockList = Iblock\IblockTable::getList([
                                                        'filter' => ['=ACTIVE' => 'Y', 'IBLOCK_TYPE_ID' => 'catalog']
                                                    ]);

        while ($arIBlock = $rsIBlockList->fetch()) {
            $property = \CIBlockProperty::GetList(
                array("sort" => "asc", "name" => "asc"),
                array(
                    "ACTIVE" => "Y",
                    "CODE" => self::IB_OBJECT_TYPE,
                    "PROPERTY_TYPE" => "L",
                    "IBLOCK_ID" => $arIBlock["ID"]
                )
            ); //

            if (!$property->SelectedRowsCount()) {
                $fieldName = Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE');
                $fieldHint = Loc::getMessage('SALE_HPS_INVOICEBOX_OBJECT_TYPE_HINT');

                if (self::MODULE_LCHARSET !== 'utf-8') {
                    $fieldName = $APPLICATION->ConvertCharset(
                        $fieldName,
                        self::MODULE_LCHARSET,
                        "utf-8"
                    );
                    $fieldHint = $APPLICATION->ConvertCharset($fieldHint, self::MODULE_LCHARSET, "utf-8");
                } //

                $arFields = array(
                    "NAME" => $fieldName,
                    "HINT" => $fieldHint,
                    "ACTIVE" => "Y",
                    "IS_REQUIRED" => "Y",
                    "SORT" => "101",
                    "CODE" => self::IB_OBJECT_TYPE,
                    "PROPERTY_TYPE" => "L",
                    "IBLOCK_ID" => $arIBlock["ID"]
                );
                $ibp = new \CIBlockProperty;
                $propetryId = $ibp->Add($arFields);
            } else {
                $tmp = $property->GetNext();
                $propetryId = $tmp["ID"];
            }

            $ibpenum = new \CIBlockPropertyEnum;
            foreach ($enumList as $_xmlId => $_name) {
                if (self::MODULE_LCHARSET !== 'utf-8') {
                    $_name = $APPLICATION->ConvertCharset($_name, self::MODULE_LCHARSET, "utf-8");
                } //

                $enum = $ibpenum->GetList(
                    array("sort" => "asc", "name" => "asc"),
                    array("ID" => $propetryId, "XML_ID" => $_xmlId)
                ); //
                if (!$enum->SelectedRowsCount()) {
                    $ibpenum->Add(
                        array(
                            "IBLOCK_ID" => $arIBlock["ID"],
                            "PROPERTY_ID" => $propetryId,
                            "VALUE" => $_name,
                            "XML_ID" => $_xmlId
                        )
                    );
                } //
            } //enumList
        } //arIBlock

        return true;
    }

    /**
     * Uninstall DB
     * @return boolean
     */
    public function UnInstallDB(): bool
    {
        return true;
    }

    /**
     * Install main files
     * @return boolean
     */
    public function InstallFiles($arParams = array(), $alternativePath = false): bool
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

        if ($countErr > 0 || !$this->InstallDB()) {
            die("ERROR");
            $this->UnInstallFiles();
            $this->UnInstallDB();
            return false;
        }

        return true;
    }

    /**
     * Uninstall main files
     * @return boolean
     */
    public function UnInstallFiles(): bool
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

        if (is_dir($p = $_SERVER['DOCUMENT_ROOT'] . $pathMod . 'modules/' . self::MODULE_ID . '/install/components')) {
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

    /**
     * Call all install methods
     * @returm void
     */
    function DoInstall()
    {
        global $USER, $APPLICATION;

        if (!$USER->IsAdmin()) {
            return;
        }

        if ($this->InstallFiles()) {
            RegisterModule(self::MODULE_ID);
        } else {
            $APPLICATION->throwException($this->strError);
        }
    }

    /**
     * Call all uninstall methods
     * @returm void
     */
    function DoUninstall()
    {
        global $USER, $APPLICATION;

        if (!$USER->IsAdmin()) {
            return;
        }

        UnRegisterModule(self::MODULE_ID);
        $this->UnInstallFiles();
    }
}
