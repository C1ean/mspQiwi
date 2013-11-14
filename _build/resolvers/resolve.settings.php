<?php

if ($object->xpdo) {
    /* @var modX $modx */
    $modx =& $object->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            $modelPath = $modx->getOption('minishop2.core_path', null, $modx->getOption('core_path') . 'components/minishop2/') . 'model/';
            $modx->addPackage('minishop2', $modelPath);
            /* @var msPayment $payment */
            $modx->removeCollection('msPayment', array('class' => 'Qiwi'));
            $modx->removeCollection('modSystemSetting', array('key:LIKE' => 'ms2\_mspqiwi\_%'));
            break;
    }
}

return true;