<?php

if (!isset($modx)) {
    define('MODX_API_MODE', true);
    require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

    $modx->getService('error', 'error.modError');
    $modx->setLogLevel(modX::LOG_LEVEL_ERROR);
}

$modx->error->message = null;
/* @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

if (!class_exists('Qiwi')) {
    exit('Error: could not load payment class "Qiwi".');
}
/* @var msPaymentInterface|Qiwi $handler */
$handler = new Qiwi($modx->newObject('msOrder'));
$config = $handler->config;

switch ($_GET['action']) { //generate Qiwi request and redirect to Qiwi
    case  'continue':
        if (!empty($_GET['msorder'])) {
            if ($order = $modx->getObject('msOrder', $_GET['msorder'])) {
                $response = $handler->send($order);
                $modx->sendRedirect($response);
            }
        }
        break;
    case 'result':
        //Going to qiwi.class to processing payment
        $handler->request();
        break;

    // Order successfully paid->redirect to success page
    case 'success':
        $url = $modx->makeUrl($config['successId'], '', array('result' => 'success'), 'full');
        $modx->sendRedirect($url);
        break;

    // Отказ от оплаты - загружаем чанк $tplFailure
    case 'failure':
        $url = $modx->makeUrl($config['failureId'], '', array('result' => 'failure'), 'full');
        $modx->sendRedirect($url);
        break;

}




