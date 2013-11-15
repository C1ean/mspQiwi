<?php
if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Qiwi extends msPaymentHandler implements msPaymentInterface
{

    function __construct(xPDOObject $object, $config = array())
    {
        $this->modx = & $object->xpdo;

        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption('assets_url') . 'components/minishop2/';
        $paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/qiwi.php';

        $this->config = array_merge(array(
            'paymentUrl' => $paymentUrl
        , 'checkoutUrl' => $this->modx->getOption('ms2_mspqiwi_url', null, 'https://w.qiwi.com/order/external/create.action') //url
        , 'shopId' => $this->modx->getOption('ms2_mspqiwi_shopId', null, '') //shopId
        , 'shopKey' => $this->modx->getOption('ms2_mspqiwi_shopKey', null, '') //shopKey
        , 'lifetime' => $this->modx->getOption('ms2_mspqiwi_lifetime', null, '24') //life cycle
        , 'check_agt' => $this->modx->getOption('ms2_mspqiwi_check_agt', null, 'false') //check agt
        , 'comment' => $this->modx->getOption('ms2_mspqiwi_comment', null, 'New Order ') //comment for qiwi payment on bill
        , 'successId' => $this->modx->getOption('ms2_mspqiwi_successId', null, '') //redirect to id when success payment
        , 'failureId' => $this->modx->getOption('ms2_mspqiwi_failureId', null, '') //redirect to id when failure payment
        , 'currency' => $this->modx->getOption('ms2_mspqiwi_currency', null, '643') //currency for Qiwi payment

        ), $config);


    }


    /* @inheritdoc} */
    public function send(msOrder $order)
    {
        if ($order->get('status') > 1) {
            return $this->error('ms2_err_status_wrong');
        }

        $http_query = $this->getPaymentLink($order);
        return $this->success('', array('redirect' => $http_query)); //return forward link

    }
    
    
    /* @inheritdoc} */
    public function getPaymentLink(msOrder $order)
    {
			$address = $order->getOne('Address');

            $request = array( //get array for requery query
        		'from' => $this->config['shopId']
    			, 'to' => $address->get('phone')
    			, 'summ' => $order->get('cost')
    			, 'com' => $this->config['comment']
    			, 'lifetime' => $this->config['lifetime']
    			, 'check_agt' => $this->config['check_agt']
    			, 'txn_id' => $order->get('id')
    			, 'currency' => $this->config['currency']
    			, 'successUrl' => $this->config['paymentUrl'] . '?action=success'
    		    , 'failUrl' => $this->config['paymentUrl'] . '?action=failure'
    		);
            $link = $this->config['checkoutUrl'] .'?'. http_build_query($request);
            return $link;
    }


    /* @inheritdoc} */
    public function receive(msOrder $order, $params = array())
    {
        /* @var miniShop2 $miniShop2 */
        $miniShop2 = $this->modx->getService('miniShop2');

        switch ($params['status']) {
            case '60': //successfull payd. Update MS2 order status to Paid
                if ($order->get('status') != 4) {
                    @$this->modx->context->key = 'mgr';
                    $miniShop2->changeOrderStatus($order->get('id'), 2);
                }
                else { //variant when order already has been canceled (status 4=canceled)
                    $miniShop2->orderLog($order->get('id'), 'status', '');

                    $this->modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Failed to change order status to Paid for txn:' . $order->get('id') . ' because order already is canceled in MS2.');

                }
                break;

            case '100': //cancel order
                $miniShop2->changeOrderStatus($order->get('id'), 4);
                break;

        }


        return true;
    }


    /**
     * Building query
     *
     * @param array $params Query params
     * @return array/boolean
     */
    public function  request()
    {

        $properties = array('classmap' => array('tns:updateBill' => 'qiwiParam', 'tns:updateBillResponse' => 'qiwiResponse'));

        $wsdl = MODX_CORE_PATH . 'components/minishop2/custom/payment/lib/qiwi/IShopClientWS.wsdl';

        $Soap = new SoapServer($wsdl, $properties);

        $Soap->setClass('qiwiServer', $this->config['shopId'], $this->config['shopKey'], $this->config['statusPaid']);
        $Soap->handle();

        return true;

    }


}


/*-------------------------------------*/
class qiwiServer
{
    public $login = null;
    public $password = null;
    public $status_paid = 0;


    public function __construct($login, $password, $status_paid)
    {

        $this->login = $login;
        $this->password = $password;
        $this->status_paid = $status_paid;


    }

    function updateBill($param)
    {
        global $modx;

        // checking SignOn
        if (!$this->checkSign($param)) {
            //if not Signed
            $resp_code = 1000;
        } else {
            //get data from MS2 about this transaction
            if ($order = $modx->getObject('msOrder', $param->txn)) {
                //get order data from qiwi
                $rc = $this->checkAccount($this->login, $this->password, $param->txn);
                // $modx->log(modX::LOG_LEVEL_ERROR,'Payment resp:'.$rc->user.' '.$rc->amount. ' '.$rc->date.' '.$rc->lifetime.' '.$rc->status);

                //creating handler to Qiwi
                /* @var msPaymentInterface|Qiwi $handler */
                $handler = new Qiwi($modx->newObject('msOrder'));

                //matching qiwi data and MS2 data for anti fraud (spoof amount in txn)
                if ($rc->amount == $order->get('cost')) { //all check complete, status is real, go to verify Qiwi status

                    //if param status in update is 60 and in qiwi status is 60 -its true payment
                    if ($param->status == 60 && $rc->status == 60) {
                        // Successfully payment
                        // get order  ($param->txn), marking as payd
                        //  $modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2] Payment OK txn:'.$param->txn. 'status:'.$param->status);

                        $params = array(
                            'status' => '60');
                        /* @var msPaymentInterface|Qiwi $handler */

                        $handler->receive($order, $params);


                        $resp_code = 0; //success payment

                    } else if ($param->status >= 100 and $rc->status >= 100) {
                        // canceled
                        // get order  ($param->txn), mark as canceled

                        $params = array(
                            'status' => '100');


                        $handler->receive($order, $params);

                        $resp_code = -2; //canceled
                    } else if ($param->status >= 50 && $param->status < 60) {
                        // under processing
                        $resp_code = -3;
                    } else if ($param->status != $rc->status) {
                        // status from input service != status in qiwi
                        $resp_code = -7;
                    } else {
                        // unknown state
                        $resp_code = -4;
                    }

                    //end of logic for statuses
                } else {
                    //when amount count mismatch
                    $resp_code = -5;
                    $modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Payment failed, txn:' . $param->txn . '$param->status:' . $param->status . ' rc->satus:' . $rc->status . ' order->cost:' . $order->get('cost') . ' $rc->amount:' . $rc->amount . ' fraud matching failed!');
                }

            } else {
                //transaction not found in MS2
                $resp_code = -6;
                $modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Could not retrieve order by Qiwi payment with id ' . $param->txn . ' because ID is not found in system.');

            }


        }
        //logic end

        if ($resp_code != 0)
            $modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2] Failed payment ' . $param->txt . ' Local resp_code: ' . $resp_code . ' Server status:' . $rc->status);

        /** @var qiwiResponse $to_srv_responce */
        $to_srv_responce = new qiwiResponse();
        $to_srv_responce->updateBillResult = 0; //send 0 (we process payment) to Qiwi permanently
        return $to_srv_responce;
    }

    //check password and login for Qiwi
    function checkSign($param)
    {
        $password = strtoupper(md5($param->txn . strtoupper(md5($this->password))));

        return ($param->password == $password) && ($param->login == $this->login);
    }

    //get information about transaction in Qiwi
    function checkAccount($login, $password, $txn)
    {

        include(MODX_CORE_PATH . 'components/minishop2/custom/payment/lib/qiwi/IShopServerWSService.php');
        $service = new IShopServerWSService(MODX_CORE_PATH . 'components/minishop2/custom/payment/lib/qiwi/IShopServerWS.wsdl',
            array('location' => 'http://ishop.qiwi.ru/services/ishop', 'trace' => TRACE));

        $params = new checkBill();

        $params->login = $login; //login
        $params->password = $password; //passwd
        $params->txn = $txn; //transaction

        //return $rc->user , $rc->amount, $rc->date, $rc->lifetime, $rc->status
        return $service->checkBill($params);
    }


}

class qiwiResponse
{
    public $updateBillResult;
}

class qiwiParam
{
    public $login;
    public $password;
    public $txn;
    public $status;
}



