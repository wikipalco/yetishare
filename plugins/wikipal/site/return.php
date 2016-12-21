<?php
header('Content-Type: text/html; charset=utf-8');
require_once('../../../core/includes/master.inc.php');
$pluginConfig   = pluginHelper::pluginSpecificConfiguration('wikipal');
$pluginSettings = $pluginConfig['data']['plugin_settings'];
$wikipal_webgate_id    = '';
$wikipal_method    = 'wsdl';
if (strlen($pluginSettings)){
	$pluginSettingsArr = json_decode($pluginSettings, true);
	$wikipal_webgate_id       = $pluginSettingsArr['wikipal_webgate_id'];
	$wikipal_method       = $pluginSettingsArr['wikipal_method'];
}
$paymentTracker = urldecode($_REQUEST['custom']);
$order          = OrderPeer::loadByPaymentTracker($paymentTracker);
if ($order)
{
	$status = 'cancelled';
	if($_POST['status'] == 'paid')
	{
		//start of wikipal
		$WebgateID = $wikipal_webgate_id;
		$Amount = intval($order->amount);
		$order_id = isset($_POST['InvoiceNumber']) ? $_POST['InvoiceNumber'] : 0;
		$tran_id = isset($_POST['authority']) ? $_POST['authority'] : 0;
		
		$Authority 			= $_POST['authority'];
		$InvoiceNumber 		= $_POST['InvoiceNumber'];

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentVerify.php');
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$WebgateID&Price=$Amount&Authority=$Authority");
		curl_setopt($curl, CURLOPT_TIMEOUT, 400);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = json_decode(curl_exec($curl));
		curl_close($curl);
		if ($result->Status == 100) {
			$status = 'completed';
			$fault = 0;
		} else {
			$status = 'failed';
			$fault = $result->Status;
		}		
	//end of wikipal
	}
	
	if ( $status == 'completed' )
	{
		$extendedDays  = $order->days;
		$upgradeUserId = $order->upgrade_user_id;
		$orderId       = $order->id;
		$userId        = $order->user_id;
		$user   = $db->getRow("SELECT * FROM users WHERE id = " . (int) $userId . " LIMIT 1");
		$to_email = SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM ? SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM : SITE_CONFIG_REPORT_ABUSE_EMAIL;
		$to_email = $to_email ? $to_email : null;
		
		// log in payment_log
		$response_vars = "Transaction_Id => ". $tran_id . "\n";
		
		$dbInsert = new DBObject("payment_log", 
			array("user_id", "date_created", "amount",
			"currency_code", "from_email", "to_email", "description",
			"request_log", "payment_method")
        );
		$dbInsert->user_id = $userId;
		$dbInsert->date_created = date("Y-m-d H:i:s", time());
		$dbInsert->amount = $order->amount;
		$dbInsert->currency_code = SITE_CONFIG_COST_CURRENCY_CODE;
		$dbInsert->from_email = $user['email'];
		$dbInsert->to_email = $to_email;
		$dbInsert->description = $extendedDays . ' days extension';
		$dbInsert->request_log = $response_vars;
        $dbInsert->payment_method = 'WikiPal';
		$dbInsert->insert();
		
		// make sure the order is pending
		if ($order->order_status == 'completed')
			header('Location: '.urldecode(WEB_ROOT . '/payment_complete.' . SITE_CONFIG_PAGE_EXTENSION));
		
		// update order status to paid
		$dbUpdate = new DBObject("premium_order", array("order_status"), 'id');
		$dbUpdate->order_status = 'completed';
		$dbUpdate->id = $orderId;
		$effectedRows = $dbUpdate->update();
		if ($effectedRows === false)
			die('متاسفانه در حین پرداخت خطایی رخ داده است');
		
		// extend/upgrade user
        $upgrade = UserPeer::upgradeUser($userId, $order->days);
        if ($upgrade === false)
			die('متاسفانه در حین پرداخت خطایی رخ داده است');

		// append any plugin includes
		pluginHelper::includeAppends('payment_ipn_paypal.php');
		header('Location: '.urldecode(WEB_ROOT . '/payment_complete.' . SITE_CONFIG_PAGE_EXTENSION));
	}

	if ( $status == 'failed' )
	{
	
		$message = "در حین پرداخت خطایی رخ داده است";
		switch($fault){
            
			case "-1" :
				$message = "اطلاعات ارسال شده ناقص است";
			break;

			case "-2" :
				$message = "شناسه درگاه نادرست است";
			break;

			case "-3" :
				$message = "با توجه به محدودیت های شاپرک امکان پرداخت با رقم درخواست شده میسر نمیباشد";
			break;
            
			case "-4" :
				$message = "سطح تایید پذیرنده پایین تر از سطح نقره ای میباشد";
			break;
            
			case "-11" :
				$message = "درخواست مورد نظر یافت نشد";
			break;
            
			case "-21" :
				$message = "هیچ نوع عملیات مالی برای این تراکنش یافت نشد";
			break;
            
			case "-22" :
				$message = "تراکنش نا موفق میباشد";
			break;
            
			case "-33" :
				$message = "رقم تراکنش با رقم وارد شده مطابقت ندارد";
			break;
            
			case "-40" :
				$message = "اجازه دسترسی به متد مورد نظر وجود ندارد";
			break;
            
			case "-54" :
				$message = "درخواست مورد نظر آرشیو شده است";
			break;
            
			case "100" :
				$message = "اتصال با ویکی پال به خوبی برقرار شد و همه چیز صحیح است";
			break;
				
			case "101" :
				$message = "تراکنش با موفقیت به پایان رسیده بود و تاییدیه آن نیز انجام شده بود";
			break;			
		}
			
		die('در حین پرداخت خطای رو به رو رخ داده است : '.$message);
	}
		
	if ( $status == 'cancelled' )
	{	
		header('Location: '.urldecode(WEB_ROOT . '/upgrade.' . SITE_CONFIG_PAGE_EXTENSION));
	}		
}
else
{
	die('متاسفانه در حین پرداخت خطایی رخ داده است');
}