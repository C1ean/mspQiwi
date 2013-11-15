<?php

$settings = array();


$tmp = array(
    'url' => array(
        'xtype' => 'textfield',
        'value' => 'https://w.qiwi.ru/setInetBill_utf.do',
    ),

    'shopId' => array(
        'xtype' => 'textfield',
        'value' => '',
    ),

    'shopKey' => array(
        'xtype' => 'text-password',
        'value' => '',
    ),

    'lifetime' => array(
        'xtype' => 'textfield',
        'value' => '24',
    ),

    'check_agt' => array(
        'xtype' => 'combo-boolean',
        'value' => 'false',
    ),

    'statusPaid' => array(
        'xtype' => 'numberfield',
        'value' => '2',
    ),

    'statusCancel' => array(
        'xtype' => 'numberfield',
        'value' => '4',
    ),

    'comment' => array(
        'xtype' => 'textfield',
        'value' => 'Оплата заказа',
    ),

    'successId' => array(
        'xtype' => 'numberfield',
        'value' => '',
    ),

    'failureId' => array(
        'xtype' => 'numberfield',
        'value' => '',
    ),

    'currency' => array(
        'xtype' => 'numberfield',
        'value' => '643',
    ),


);

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(
        array(
            'key' => 'ms2_mspqiwi_'.$k,
            'namespace' => 'minishop2',
            'area' => 'ms2_payment',
        ), $v
    ), '', true, true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;
