<?php
header('Content-Type: text/html; charset=utf-8');
require_once('../../../core/includes/master.inc.php');
$pluginConfig   = pluginHelper::pluginSpecificConfiguration('wikipal');
$pluginSettings = $pluginConfig['data']['plugin_settings'];
$wikipal_webgate_id    = '';
if (strlen($pluginSettings)){
    $pluginSettingsArr = json_decode($pluginSettings, true);
    $wikipal_webgate_id       = $pluginSettingsArr['wikipal_webgate_id'];
    $wikipal_method       = $pluginSettingsArr['wikipal_method'];
}
if (!isset($_REQUEST['days'])){
    coreFunctions::redirect(WEB_ROOT . '/index.html');
}
// require login
if (!isset($_REQUEST['i'])){
    $Auth->requireUser(WEB_ROOT.'/login.'.SITE_CONFIG_PAGE_EXTENSION);
    $userId    = $Auth->id;
    $username  = $Auth->username;
    $userEmail = $Auth->email;
}
else
{
    $user = UserPeer::loadUserByIdentifier($_REQUEST['i']);
    if (!$user)
    {
        die('همچین کاربری وجود ندارد');
    }

    $userId    = $user->id;
    $username  = $user->username;
    $userEmail = $user->email;
}
$days = (int) (trim($_REQUEST['days']));
$fileId = null;
if (isset($_REQUEST['f'])){
    $file = file::loadByShortUrl($_REQUEST['f']);
    if ($file){
        $fileId = $file->id;
    }
}
// create order entry
$orderHash = MD5(time() . $userId);
$amount    = intval(constant('SITE_CONFIG_COST_FOR_' . $days . '_DAYS_PREMIUM'))*10;
$order     = OrderPeer::create($userId, $orderHash, $days, $amount, $fileId);
$return_url = urldecode(PLUGIN_WEB_ROOT . '/' . $pluginConfig['data']['folder_name'] . '/site/return.php').'?custom='.urlencode($orderHash).'&mc_gross='.$amount;
if ($order)
{    
	//start of wikipal
	$order_id = $order->id;
	$Description = 'خرید اکانت '.intval($days).' روزه کاربر '.$username; 

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentRequest.php');
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
	curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$wikipal_webgate_id&Price=$amount&Description=$Description&InvoiceNumber=$order_id&CallbackURL=". urlencode($return_url));
	curl_setopt($curl, CURLOPT_TIMEOUT, 400);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$result = json_decode(curl_exec($curl));
	curl_close($curl);
	
	if ($result->Status == 100)
	{
		header('Location: http://gatepay.co/webservice/startPayment.php?au='. $result->Authority);
	} else {
		$message = "در حین پرداخت خطایی رخ داده است";
		
		if($result->Status == '-1'){
			$message = "پارامترهای ارسال ناقص میباشد";
		}
		elseif ($result->Status =='-2'){
			$message = "مرچنت کد ارسال شده صحیح نمیباشد";
		}
		elseif ($result->Status =='-3'){
			$message = "مرچنت کد ( درگاه مورد نظر ) غیر فعال میباشد";
		}
		elseif ($result->Status =='-4'){
			$message = "مقدار پارامتر Price باید یک عدد صحیح برابر یا بزرگتر از 100 باشد ( حداقل مبلغ قابل پرداخت 100 تومان میباشد )";
		}
		elseif ($result->Status =='-5'){
			$message = "مقدار InvoiceNumber باید یک عدد صحیح بزرگتر از 0 باشد";
		}
		elseif ($result->Status =='-6'){
			$message = "خطای سیستمی در ایجاد Authority, این موضوع را به بخش پشتیبانی ویکی پال اطلاع دهید";
		}
		elseif ($result->Status =='-7'){
			$message = "خطا در دریافت Authority, این موضوع را به پشتیبانی ویکی پال اطلاع دهید";
		}
		elseif ($result->Status =='-8'){
			$message = "خطای سیستمی, این موضوع را به پشتیبانی ویکی پال اطلاع دهید";
		}
		else{
			$message = "پاسخی دریافت نشد لطفا مجدد تلاش کنید";
		}
		echo $amount;
		die('در حین پرداخت خطای رو به رو رخ داده است : '.$message);
	}
	//end of wikipal
}