<?php

if (!isset($modx)) {
    define('MODX_API_MODE', true);
    require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

    $modx->getService('error','error.modError');
    $modx->setLogLevel(modX::LOG_LEVEL_ERROR);
}

$modx->error->message = null;
/* @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

if (!class_exists('Qiwi')) {exit( 'Error: could not load payment class "Qiwi".');}
/* @var msPaymentInterface|Qiwi $handler */
$handler = new Qiwi($modx->newObject('msOrder'));

switch ($_GET['action']) { //generate Qiwi request and redirect to Qiwi
    case  'continue':
        if (!empty($_GET['msorder']) && !empty($_GET['mscode'])) {
            if ($order = $modx->getObject('msOrder', $_GET['msorder'])) {
                if ($_GET['mscode'] == $handler->getOrderHash($order)) {
                    $response = $handler->send($order);
                    $modx->sendRedirect($response);
                }
            }
        }
        break;
    case 'result':
        $config=$handler->config;

        $properties = array('classmap' => array('tns:updateBill' => 'Param', 'tns:updateBillResponse' => 'Response'));

        $wsdl=MODX_CORE_PATH.'components/minishop2/custom/payment/lib/qiwi/IShopClientWS.wsdl';

        $Soap = new SoapServer($wsdl, $properties);
        $Soap->setClass('Server', $config['shopId'],$config['shopKey'],$config['statusPaid']);
        $Soap->handle();

        break;

    // Order successfully paid->redirect to success page
    case 'success':
        $url = $modx->makeUrl($config['successId'],'',array('result' => 'success'),'full');
        $modx->sendRedirect($url);
        break;

    // Отказ от оплаты - загружаем чанк $tplFailure
    case 'failure':
        $url = $modx->makeUrl($config['failureId'],'',array('result' => 'failure'),'full');
        $modx->sendRedirect($url);
        break;

}







/*-------------------------------------*/
class Server {
    public $login = null;
    public $password = null;
    public $status_paid = 0;


    public function __construct($login, $password, $status_paid) {
        $this->login = $login;
        $this->password = $password;
        $this->status_paid = $status_paid;




    }

    function updateBill($param) {
        global $modx;

        // checking SignOn
        if (!$this->checkSign($param)) {
             //if not Signed
            $resp_code = 1000;
        }
        else {
             //get data from MS2 about this transaction
            if ($order = $modx->getObject('msOrder',$param->txn))
            {
                //get order data from qiwi
                $rc=$this->checkAccount($this->login,$this->password,$param->txn);
                //$modx->log(modX::LOG_LEVEL_ERROR,'Payment resp:'.$rc->user.' '.$rc->amount. ' '.$rc->date.' '.$rc->lifetime.' '.$rc->status);
                //matching qiwi data and MS2 data for antifrood (spoof amount in txn)
                if ($rc->amount==$order->get('cost'))
                { //all check complete, status is real, go to verify Qiwi status

                    if ($param->status == 60) {
                        // Successfully payment
                        // get order  ($param->txn), marking as payd
                      //  $modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2] Payment OK txn:'.$param->txn. 'status:'.$param->status);

                        $params = array(
                            'status' => '60');
                        /* @var msPaymentInterface|Qiwi $handler */
                        $handler = new Qiwi($modx->newObject('msOrder'));
                        $handler->receive($order,$params);


                        $resp_code=0; //success payment

                    } else if ($param->status >= 100) {
                        // canceled
                        // get order  ($param->txn), mark as canceled

                        $params = array(
                            'status' => '100');
                        /* @var msPaymentInterface|PayPal $handler */
                        $handler = new Qiwi($modx->newObject('msOrder'));
                        $handler->receive($order,$params);

                        $resp_code=-2;//canceled
                    } else if ($param->status >= 50 && $param->status < 60) {
                        // under processing
                        $resp_code=-3;
                    } else {
                        // unknown state
                        $resp_code=-4;
                    }

                    //end of logic for statuses
                }else {
                    //when amount count mismatch
                   $resp_code=-5;
                   $modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2] Payment field, txn:'.$param->txn. '$param->status:'.$param->status.' rc->satus:'.$rc->status. ' order->cost:'.$order->get('cost'). ' $rc->amount:'.$rc->amount.' fraud matching failed!');
                }

            }
            else
            {
                //transaction not found in MS2
                $resp_code=-6;
                $modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Could not retrieve order by Qiwi payment with id '.$param->txn.' because ID is not found in system.');

            }


        }
          //logic end

            if ($resp_code!=0)
                $modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Field payment.Local resp_code: '.$resp_code.' Server status:'.$rc->status);


            $to_srv_responce = new Response();
            $to_srv_responce->updateBillResult = 0; //send 0 (we process payment) to Qiwi permanently
            return $to_srv_responce;
    }

     //check password and login for Qiwi
    function checkSign($param) {
        $password = strtoupper(md5($param->txn . strtoupper(md5($this->password))));

        return ($param->password == $password) && ($param->login == $this->login);
    }

      //get information about transaction in Qiwi
    function checkAccount( $login,$password, $txn ) {

        include(MODX_CORE_PATH.'components/minishop2/custom/payment/lib/qiwi/IShopServerWSService.php');
        $service = new IShopServerWSService(MODX_CORE_PATH.'components/minishop2/custom/payment/lib/qiwi/IShopServerWS.wsdl',
            array('location'      => 'http://ishop.qiwi.ru/services/ishop', 'trace' => TRACE));

        $params=new checkBill();

        $params->login = $login;//login
        $params->password = $password; //passwd
        $params->txn =$txn; //transaction

        //return $rc->user , $rc->amount, $rc->date, $rc->lifetime, $rc->status
        return $service->checkBill($params);
    }



}

class Response {public $updateBillResult;}
class Param {public $login;public $password;public $txn;public $status;}



