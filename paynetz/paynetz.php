<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * @version $Id: paynetz.php,v 1.4 2005/05/27 19:33:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Max Milbers, Valérie Isaksen
 * @version $Id: paynetz.php 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

	class plgVmPaymentPaynetz extends vmPSPlugin {

	function __construct (& $subject, $config) {
		
		parent::__construct ($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment paynetz Table');
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)'
		);

		return $SQLfields;
	}

	/**
	 *
	 *
	 * @author Valérie Isaksen
	 */
	function plgVmConfirmedOrder ($cart, $order) {
	
		$paymentId = $order['details']['BT']->virtuemart_paymentmethod_id;
		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		
		$lang = JFactory::getLanguage ();
		$filename = 'com_virtuemart';
		$lang->load ($filename, JPATH_ADMINISTRATOR);
		
		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		
		$this->getPaymentCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		$email_currency = $this->getEmailCurrency($method);

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);

		$dbValues['payment_name'] = $this->renderPluginName ($method) . '<br />' . $method->payment_info;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);
		
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		
		$param = array(
		   "login_id" => $method->api_login_id
		   ,"password" => $method->api_password
		   ,"prod_id" => $method->api_product_id
		   ,"ttype" => "NBFundTransfer"
		   ,"ordernum" => $dbValues['order_number']
		   ,"amount" =>   $order['details']['BT']->order_total
		   ,"curr" => "INR"
		   ,"txnamt" => "0"
		   ,"client_code" => "007"
		   ,"customer_acc_no" => "1234567890"
		   ,"paynetz_url" => $method->api_merchant_url
		);
		//$this->updateRecords( $dbValues['order_number'], $order['details']['BT']->order_total, $d );
		
		$this->requestMerchant($param,$paymentId);
		/*
		$app = JFactory::getApplication();
		$app->redirect($url);
		*/
		$html = '<table class="vmorder-done">' . "\n";
		$html .= $this->getHtmlRow ('paynetz_PAYMENT_INFO', $dbValues['payment_name'], 'class="vmorder-done-payinfo"');
		if (!empty($payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = JText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
			$html .= $this->getHtmlRow ('paynetz_PAYMENTINFO', $payment_info, 'class="vmorder-done-payinfo"');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}
		
		$html .= $this->getHtmlRow ('paynetz_ORDER_NUMBER', $order['details']['BT']->order_number, 'class="vmorder-done-nr"');
		$html .= $this->getHtmlRow ('paynetz_AMOUNT', $currency->priceDisplay ($order['details']['BT']->order_total), 'class="vmorder-done-amount"');

		if ($method->payment_currency != $order['details']['BT']->order_currency) {
			$html .= $this->getHtmlRow ('COM_VIRTUEMART_CART_TOTAL_PAYMENT', $totalInPaymentCurrency['display'], 'class="vmorder-done-amount"');
		}

		//$html .= $this->getHtmlRow('paynetz_INFO', $method->payment_info);
		//$html .= $this->getHtmlRow('paynetz_AMOUNT', $totalInPaymentCurrency.' '.$currency_code_3);
		$html .= '</table>' . "\n";

		$modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $this->getNewStatus ($method);
		$order['customer_notified'] = 1;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart ();
		JRequest::setVar ('html', $html);
		return TRUE;
	}

	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('PAYNETZ_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('PAYNETZ_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

/*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

		if (preg_match ('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}
*/
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);


		//vmdebug('paynetz checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
 		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}


	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the paynetz method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */

	function plgVmOnUserInvoice ($orderDetails, &$data) {

		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}
		/**
		 * @param $virtuemart_paymentmethod_id
		 * @param $paymentCurrencyId
		 * @return bool|null
		 */
		function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

			if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
				return NULL; // Another method was selected, do nothing
			}
			if (!$this->selectedThisElement($method->payment_element)) {
				return FALSE;
			}
			if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
				// JError::raiseWarning(500, $db->getErrorMsg());
				return '';
			}
			if (empty($payments[0]->email_currency)) {
				$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
				$db = JFactory::getDBO();
				$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
				$db->setQuery($q);
				$emailCurrencyId = $db->loadResult();
			} else {
				$emailCurrencyId = $payments[0]->email_currency;
			}

		}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment ($name, $id, &$data) {

		return $this->declarePluginParams ('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param         $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int     $virtuemart_order_id : payment  order id
	 * @param char    $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int     $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text    $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 *
	function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
	return null;
	}
	 */
	 
	 function plgVmOnPaymentNotification () {

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$virtuemart_paymentmethod_id = JRequest::getInt('virtuemart_paymentmethod_id', '');
		$order_number = JRequest::getString('mer_txn', '');

		$status = JRequest::getString('f_code', '');
		
		if (!isset($order_number)) {
			return;
		}
		
		$method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id);
		
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return;
		}
			
		$modelOrder = VmModel::getModel ('orders');
		$vmorder = $modelOrder->getOrder ($virtuemart_order_id);
		
		$order = array();

		$order['customer_notified'] = 1;

		if(strtolower($status) == "ok"){
			$order['order_status'] = "C";
			$order['comments'] = JText::sprintf ('VMPAYMENT_PAYNETZ_PAYMENT_STATUS_CONFIRMED', $virtuemart_order_id);
		} else {
			$order['comments'] = JText::sprintf ('VMPAYMENT_PAYNETZ_PAYMENT_STATUS_FAILED', $virtuemart_order_id);
			$order['order_status'] = "X";
			$this->_handlePaymentCancel($virtuemart_order_id, $html);
			return;
		}

		$this->logInfo ('plgVmOnPaymentNotification return new_status:' . $order['order_status'], 'message');
		
		$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);

		//// remove vmcart
		$this->emptyCart($payment->user_session, $mb_data['transaction_id']);
		$html = 'Thank you for order. Your transaction ID is '. $virtuemart_order_id . "\n";

		JRequest::setVar('html', $html);
		
		$mainframe = JFactory::getApplication();
		$mainframe->enqueueMessage($html);
		$mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=confirm',FALSE));
		return;
	}
	
	function _handlePaymentCancel ($virtuemart_order_id, $html)
	{

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		//$modelOrder = VmModel::getModel('orders');
		//$modelOrder->remove(array('virtuemart_order_id' => $virtuemart_order_id));
		// error while processing the payment
		$mainframe = JFactory::getApplication();
		$mainframe->enqueueMessage($html);
		$mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment',FALSE), JText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID'));
		return;
	}
	
	public function plgVmOnPaymentNotification_old() {
	
	$virtuemart_paymentmethod_id = JRequest::getInt('virtuemart_paymentmethod_id', '');
	$order_id = JRequest::getString('mer_txn', '');
	
	$status = JRequest::getString('f_code', '');
	
	if (empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id) or empty($order_id)) {
		return NULL;
	}
	if (!($this->method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
		return NULL; // Another method was selected, do nothing
	}

	if(strtolower($status) == "ok"){
		
	}
	echo $virtuemart_paymentmethod_id.$order_id;exit;
	return null;
	} 
	function plgVmOnPaymentResponseReceived(&$virtuemart_order_id, &$html) {
		echo $virtuemart_order_id;
		print_r($html);exit;
		return null;
	} 
	function requestMerchant($param,$paymentId){
		$_url  = '';
		
		$returnUrl =  urlencode(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&virtuemart_paymentmethod_id=' . $paymentId . '&Itemid=' . JRequest::getInt('Itemid').'&order_id='.$param['ordernum']);
			
		$datenow = date("d/m/Y h:m:s");
		$encodedDate = str_replace(" ", "%20", $datenow);
		$_url = $_url.$param['paynetz_url'];
		$postFields  = "";
		$postFields .= "&login=".$param['login_id'];
		$postFields .= "&pass=".$param['password'];
		$postFields .= "&ttype=".$param['ttype'];
		$postFields .= "&prodid=".$param['prod_id'];
		$postFields .= "&amt=".$param['amount'];
		$postFields .= "&txncurr=".$param['curr'];
		$postFields .= "&txnscamt=".$param['txnamt'];
		$postFields .= "&clientcode=".urlencode(base64_encode($param['client_code']));
		$postFields .= "&txnid=".$param['ordernum'];
		$postFields .= "&date=".$encodedDate;
		$postFields .= "&custacc=".$param['customer_acc_no'];
		$postFields .= "&ru=".$returnUrl;
		$sendUrl = $_url."?".substr($postFields,1)."\n";

		$returnData = $this->curlExec($sendUrl);
		$xmlObjArray     = $this->xmltoarray($returnData);
		
		$url = $xmlObjArray['url'];
		$postFields  = "";
		$postFields .= "&ttype=".$param['ttype'];
		$postFields .= "&tempTxnId=".$xmlObjArray['tempTxnId'];
		$postFields .= "&token=".$xmlObjArray['token'];
		$postFields .= "&txnStage=1";
		$url = $_url."?".$postFields;
		header("Location: ".$url);
	}

	function xmltoarray($data){
		$parser = xml_parser_create('');
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); 
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, trim($data), $xml_values);
		xml_parser_free($parser);
		
		$returnArray = array();
		$returnArray['url'] = $xml_values[3]['value'];
		$returnArray['tempTxnId'] = $xml_values[5]['value'];
		$returnArray['token'] = $xml_values[6]['value'];

		return $returnArray;
	}

	function curlExec($base_url){
		$ch = curl_init($base_url);
		curl_setopt_array($ch, array(
		CURLOPT_URL            => $base_url,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
		CURLOPT_SSL_VERIFYPEER =>0,
		CURLOPT_SSL_VERIFYHOST => 0
	  ));

	  $results = curl_exec($ch);
	  return $results;
	}

}

// No closing tag
