<?php

if (IsModuleInstalled('invoicebox.payment')) {
    RegisterModuleDependences(
        'sale',
        'OnGetBusinessValueGroups',
        'invoicebox.payment',
        'CInvoicebox',
        'getBusValueGroups'
    );

    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/tools/invoicebox')) {
        $updater->CopyFiles('install/tools/invoicebox/', '/local/tools/invoicebox/');
    }
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/sale_payment/invoicebox/')) {
        $updater->CopyFiles('install/sale_payment/', '/local/php_interface/include/sale_payment/');
    }

    $updater->CopyFiles('install/tools/', 'tools/');
    $updater->CopyFiles('install/sale_payment/', 'php_interface/include/sale_payment/');
    $updater->CopyFiles('install/sale_payment/invoicebox/images/', 'images/sale/sale_payments/');
}
