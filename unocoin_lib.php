<?php

require_once 'unocoin_options.php';

function unocoinCurl($url, $apiKey, $post = false) {
	global $unocoinOptions;	
		
	$curl = curl_init($url);
	$length = 0;
	if ($post)
	{	
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		$length = strlen($post);
	}
	
	$uname = base64_encode($apiKey);
	$header = array(
		'Content-Type: application/json',
		"Content-Length: $length",
		"Authorization: Basic $uname",
		);

	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		
	$responseString = curl_exec($curl);
	
	if($responseString == false) {
		$response = curl_error($curl);
	} else {
		$response = json_decode($responseString, true);
	}
	curl_close($curl);
	return $response;
}
// $orderId: Used to display an orderID to the buyer. In the account summary view, this value is used to 
// identify a ledger entry if present.
//
// $price: by default, $price is expressed in the currency you set in unocoin_options.php.  The currency can be 
// changed in $options.
//
// $posData: this field is included in status updates or requests to get an invoice.  It is intended to be used by
// the merchant to uniquely identify an order associated with an invoice in their system.  Aside from that, Bit-Pay does
// not use the data in this field.  The data in this field can be anything that is meaningful to the merchant.
//
// $options keys can include any of: 
// ('itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 'apiKey'
//		'currency', 'physical', 'fullNotifications', 'transactionSpeed', 'buyerName', 
//		'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone')
// If a given option is not provided here, the value of that option will default to what is found in unocoin_options.php
// (see api documentation for information on these options).
function unocoinCreateInvoice($orderId, $price, $posData, $options = array()) {	
	global $unocoinOptions;	
	
	$options = array_merge($unocoinOptions, $options);	// $options override any options found in unocoin_options.php
	
	$pos = array('posData' => $posData);
	if ($unocoinOptions['verifyPos'])
		$pos['hash'] = crypt(serialize($posData), $options['apiKey']);
	$options['posData'] = json_encode($pos);
	
	$options['orderID'] = $orderId;
	$options['price'] = $price;
	
	$postOptions = array('orderID', 'itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 
		'posData', 'price', 'currency', 'physical', 'fullNotifications', 'transactionSpeed', 'buyerName', 
		'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone');
	foreach($postOptions as $o)
		if (array_key_exists($o, $options))
			$post[$o] = $options[$o];
	$post = json_encode($post);
	
	$response = unocoinCurl('https://www.unocoin.com/account/notifications', $options['apiKey'], $post);	
	if (is_string($response))
		return array('error' => $response);	

	return $response;
}

// Call from your notification handler to convert $_POST data to an object containing invoice data
function unocoinVerifyNotification($apiKey = false) {
	global $unocoinOptions;
	if (!$apiKey)
		$apiKey = $unocoinOptions['apiKey'];		
	
	$post = file_get_contents("php://input");
	if (!$post)
		return array('error' => 'No post data');
		
	$json = json_decode($post, true);	
	if (is_string($json))
		return array('error' => $json); // error

	if (!array_key_exists('posData', $json)) 
		return array('error' => 'no posData');
		
	// decode posData
	$posData = json_decode($json['posData'], true);
	if($unocoinOptions['verifyPos'] and $posData['hash'] != crypt(serialize($posData['posData']), $apiKey)) 
		return array('error' => 'authentication failed (bad hash)');
	$json['posData'] = $posData['posData'];
		
	return $json;
}

// $options can include ('apiKey')
function unocoinGetInvoice($invoiceId, $apiKey=false) {
	global $unocoinOptions;
	if (!$apiKey)
		$apiKey = $unocoinOptions['apiKey'];		

	$response = unocoinCurl('https://www.unocoin.com/account/notifications'.$invoiceId, $apiKey);
	if (is_string($response))
		return array('error' => $response); 
	//decode posData
	$response['posData'] = json_decode($response['posData'], true);
	$response['posData'] = $response['posData']['posData'];

	return $response;	
}


?>
