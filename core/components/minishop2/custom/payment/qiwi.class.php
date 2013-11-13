<?php
if (!class_exists('msPaymentInterface')) {
	require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class Qiwi extends msPaymentHandler implements msPaymentInterface {

	function __construct(xPDOObject $object, $config = array()) {
		$this->modx = & $object->xpdo;

		$siteUrl = $this->modx->getOption('site_url');
		$assetsUrl = $this->modx->getOption('assets_url').'components/minishop2/';
		$paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/qiwi.php';

		$this->config = array_merge(array(
			'paymentUrl' => $paymentUrl
			,'checkoutUrl' => $this->modx->getOption('ms2_mspqiwi_url', null, 'https://w.qiwi.ru/setInetBill_utf.do') //url
			,'shopId' => $this->modx->getOption('ms2_mspqiwi_shopId', null, '') //shopId
            ,'shopKey' => $this->modx->getOption('ms2_mspqiwi_shopKey', null, '') //shopKey
            ,'lifetime' => $this->modx->getOption('ms2_mspqiwi_lifetime', null, '24') //life cycle
            ,'check_agt' => $this->modx->getOption('ms2_mspqiwi_check_agt', null, 'false') //check agt
            ,'statusPaid' => $this->modx->getOption('ms2_mspqiwi_statusPaid', null, '2') //success MS2 status
            ,'statusCancel' => $this->modx->getOption('ms2_mspqiwi_statusCancel', null, '4') //cancel MS2 status
            ,'comment'   => $this->modx->getOption('ms2_mspqiwi_comment', null, 'New Order ') //comment for qiwi payment on bill
            ,'successId'   => $this->modx->getOption('ms2_mspqiwi_successId', null, '')  //redirect to id when success payment
            ,'failureId'   => $this->modx->getOption('ms2_mspqiwi_failureId', null, '')  //redirect to id when failure payment
            ,'currency'   => $this->modx->getOption('ms2_mspqiwi_currency', null, '643')  //currency for Qiwi payment
        ), $config);
	}


	/* @inheritdoc} */
	public function send(msOrder $order) {
		if ($order->get('status') > 1) {
			return $this->error('ms2_err_status_wrong');
		}

        $address_data = $order->getOne('Address')->toArray('address.'); //get address information

        $request = array( //get array for requery query
        'from' => $this->config['shopId']
        ,'to' => $address_data["address.phone"]
        ,'summ' => $order->get('cost')
        ,'com' => $this->config['comment']
        ,'lifetime' => $this->config['lifetime']
        ,'check_agt' => $this->config['check_agt']
        ,'txn_id' => $order->get('id')
        ,'currency' => $this->config['currency']

        );

        $http_query=$this->config['checkoutUrl'].'?'.http_build_query($request); //generate forward link

        return $this->success('', array('redirect' => $http_query)); //return forward link

   	}


	/* @inheritdoc} */
	public function receive(msOrder $order, $params = array()) {
		/* @var miniShop2 $miniShop2 */
		$miniShop2 = $this->modx->getService('miniShop2');

        switch ($params['status']) {
            case '60': //successfull payd. Update MS2 order status to Paid
                if ($order->get('status')!=4)
                $miniShop2->changeOrderStatus($order->get('id'), $this->config['statusPaid']);
                else { //variant when order already has been canceled (status 4=canceled)
                $miniShop2->orderLog($order->get('id'),'status','');
                $this->modx->log(modX::LOG_LEVEL_ERROR,'[miniShop2] Field to change order status for txn:'.$order->get('id').' :order already is canceled.');

                }
                break;

            case '100': //cancel order
                $miniShop2->changeOrderStatus($order->get('id'), $this->config['statusCancel']);
            break;

        }


		return true;
	}




}