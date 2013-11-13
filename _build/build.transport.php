<?php
/**
 * mspRobokassa build script
 *
 * @package msprobokassa
 * @subpackage build
 */
$mtime = microtime();
$mtime = explode(' ', $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;
set_time_limit(0);

require_once 'build.config.php';

/* define sources */
$root = dirname(dirname(__FILE__)).'/';
$sources = array(
    'root' => $root,
    'build' => $root . '_build/',
    'data' => $root . '_build/data/',
    'resolvers' => $root . '_build/resolvers/',
    'source_assets' => array(
        'components/minishop2/payment/qiwi.php'
    ),
    'source_core' => array(
        'components/minishop2/custom/payment/lib/qiwi/',
        'components/minishop2/custom/payment/qiwi.class.php',
        'components/minishop2/lexicon/en/msp.qiwi.inc.php',
        'components/minishop2/lexicon/ru/msp.qiwi.inc.php',
            )
,'docs' => $root . 'docs/'
);
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once $sources['build'] . '/includes/functions.php';

$modx= new modX();
$modx->initialize('mgr');
echo '<pre>'; /* used for nice formatting of log messages */
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$modx->loadClass('transport.modPackageBuilder','',false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER,PKG_VERSION,PKG_RELEASE);
$modx->log(modX::LOG_LEVEL_INFO,'Created Transport Package.');

/* load system settings */
if (defined('BUILD_SETTING_UPDATE')) {
    $settings = include $sources['data'].'transport.settings.php';
    if (!is_array($settings)) {
        $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in settings.');
    } else {
        $attributes= array(
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => BUILD_SETTING_UPDATE,
        );
        foreach ($settings as $setting) {
            $vehicle = $builder->createVehicle($setting,$attributes);
            $builder->putVehicle($vehicle);
        }
        $modx->log(modX::LOG_LEVEL_INFO,'Packaged in '.count($settings).' System Settings.');
    }
    unset($settings,$setting,$attributes);
}


/* @var msPayment $payment */
$payment= $modx->newObject('msPayment');
$payment->fromArray(array(
    'name' => 'Qiwi'
,'active' => 0
,'class' => 'Qiwi'
));

/* create payment vehicle */
$attributes = array(
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => false
);
$vehicle = $builder->createVehicle($payment,$attributes);

$modx->log(modX::LOG_LEVEL_INFO,'Adding file resolvers to payment...');
foreach($sources['source_assets'] as $file) {
    $dir = dirname($file) . '/';
    $vehicle->resolve('file',array(
        'source' => $root . 'assets/' . $file,
        'target' => "return MODX_ASSETS_PATH . '{$dir}';",
    ));
}
foreach($sources['source_core'] as $file) {
    $dir = dirname($file) . '/';
    $vehicle->resolve('file',array(
        'source' => $root . 'core/'. $file,
        'target' => "return MODX_CORE_PATH . '{$dir}';"
    ));
}
unset($file, $attributes);

$resolvers = array('settings');
foreach ($resolvers as $resolver) {
    if ($vehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.'.$resolver.'.php'))) {
        $modx->log(modX::LOG_LEVEL_INFO,'Added resolver "'.$resolver.'" to category.');
    }
    else {
        $modx->log(modX::LOG_LEVEL_INFO,'Could not add resolver "'.$resolver.'" to category.');
    }
}

flush();
$builder->putVehicle($vehicle);

/* now pack in the license file, readme and setup options */
$builder->setPackageAttributes(array(
    'changelog' => file_get_contents($sources['docs'] . 'changelog.txt')
,'license' => file_get_contents($sources['docs'] . 'license.txt')
,'readme' => file_get_contents($sources['docs'] . 'readme.txt')
    /*
    ,'setup-options' => array(
            'source' => $sources['build'].'setup.options.php',
    ),
    */
));
$modx->log(modX::LOG_LEVEL_INFO,'Added package attributes and setup options.');

/* zip up package */
$modx->log(modX::LOG_LEVEL_INFO,'Packing up transport package zip...');
$builder->pack();
$modx->log(modX::LOG_LEVEL_INFO,"\n<br />Package Built.<br />");

$mtime= microtime();
$mtime= explode(" ", $mtime);
$mtime= $mtime[1] + $mtime[0];
$tend= $mtime;
$totalTime= ($tend - $tstart);
$totalTime= sprintf("%2.4f s", $totalTime);

if (defined('PKG_AUTO_INSTALL') && PKG_AUTO_INSTALL) {
    $signature = $builder->getSignature();
    $sig = explode('-',$signature);
    $versionSignature = explode('.',$sig[1]);

    /* @var modTransportPackage $package */
    if (!$package = $modx->getObject('transport.modTransportPackage', array('signature' => $signature))) {
        $package = $modx->newObject('transport.modTransportPackage');
        $package->set('signature', $signature);
        $package->fromArray(array(
            'created' => date('Y-m-d h:i:s'),
            'updated' => null,
            'state' => 1,
            'workspace' => 1,
            'provider' => 0,
            'source' => $signature.'.transport.zip',
            'package_name' => $sig[0],
            'version_major' => $versionSignature[0],
            'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
            'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
        ));
        if (!empty($sig[2])) {
            $r = preg_split('/([0-9]+)/',$sig[2],-1,PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($r) && !empty($r)) {
                $package->set('release',$r[0]);
                $package->set('release_index',(isset($r[1]) ? $r[1] : '0'));
            } else {
                $package->set('release',$sig[2]);
            }
        }
        $package->save();
    }
    $package->install();
}

$modx->log(modX::LOG_LEVEL_INFO,"\n<br />Execution time: {$totalTime}\n");
echo '</pre>';