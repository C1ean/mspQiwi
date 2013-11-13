<?php

$settings = array();


$tmp = array(
	'ms2_mspqiwi_url' => array(
		'xtype' => 'textfield',
		'value' => 'https://w.qiwi.ru/setInetBill_utf.do',
		'area' => 'mspqiwi',
	),

    'ms2_mspqiwi_shopId' => array(
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_shopKey' => array(
        'xtype' => 'text-password',
        'value' => '',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_lifetime' => array(
        'xtype' => 'textfield',
        'value' => '24',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_check_agt' => array(
        'xtype' => 'combo-boolean',
        'value' => 'false',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_statusPaid' => array(
        'xtype' => 'numberfield',
        'value' => '2',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_statusCancel' => array(
        'xtype' => 'numberfield',
        'value' => '4',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_comment' => array(
        'xtype' => 'textfield',
        'value' => 'Оплата заказа',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_successId' => array(
        'xtype' => 'numberfield',
        'value' => '',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_failureId' => array(
        'xtype' => 'numberfield',
        'value' => '',
        'area' => 'mspqiwi',
    ),

    'ms2_mspqiwi_currency' => array(
        'xtype' => 'numberfield',
        'value' => '643',
        'area' => 'mspqiwi',
    ),



);

foreach ($tmp as $k => $v) {
	/* @var modSystemSetting $setting */
	$setting = $modx->newObject('modSystemSetting');
	$setting->fromArray(array_merge(
		array(
			'key' =>$k,
			'namespace' => 'minishop2',
		), $v
	),'',true,true);

	$settings[] = $setting;
}

unset($tmp);
return $settings;
