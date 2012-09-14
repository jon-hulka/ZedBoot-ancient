<?php
/**
 * I'm not sure what the legal/license status of this is.
 * It is adapted from the example code at https://www.x.com/developers/paypal/documentation-tools/paypal-code-samples
 * I would put a GPL license on it, but I don't know if I am entitled to do that.
 * Jon Hulka (jon.hulka@gmail.com)
 */
namespace cms\utilities;
/**
 * Some paypal API sample code wrapped up in a class
 */
class PaypalExpressCheckoutNVP
{
	const
		ENV_SANDBOX=0,
		ENV_BETA_SANDBOX=1,
		ENV_LIVE=2,
		API_VERSION='72.0';
	private
		$errorMessage='',
		$username=false,
		$password=false,
		$signature=false,
		$environment=false,
		$shipTo=array(),
		$shippingNVP='&NOSHIPPING=1',
		$shippingTotal=0,
		$maxShipping=0,
		$taxNVP='&PAYMENTREQUEST_0_TAXAMT=0.00',
		$taxTotal=0,
		$maxTax=0;
		
	private static
		$endpoints=array(self::ENV_SANDBOX=>'https://api-3t.sandbox.paypal.com/nvp',self::ENV_BETA_SANDBOX=>'https://api-3t.beta-sandbox.paypal.com/nvp',self::ENV_LIVE=>'https://api-3t.paypal.com/nvp'),
		$paypalURLs=array(self::ENV_SANDBOX=>'https://www.sandbox.paypal.com/webscr',self::ENV_BETA_SANDBOX=>'https://www.beta-sandbox.paypal.com/webscr',self::ENV_LIVE=>'https://www.paypal.com/webscr');
	public function __construct($username,$password,$signature,$environment=self::ENV_SANDBOX)
	{
		$this->username=$username;
		$this->password=$password;
		$this->signature=$signature;
		$this->environment=$environment;
	}

	public function getShippingAddress(){ return $this->shipTo; }
	public function getErrorMessage(){ return $this->errorMessage; }
	
	/**
	 * Call this before setExpressCheckout.
	 * @param array|boolean|number $shipping Shipping options (array), false for no shipping, or shipping amount (number) to include shipping amount but not shipping information.
	 */
	public function setShipping($shipping)
	{
		$this->shippingNVP='';
		if(is_array($shipping))
		{
			if(count($shipping)>0)
			{
				$this->shippingNVP=self::getShippingOptionsNVP($shipping,$this->maxShipping,$this->shippingTotal);
			}
			$this->shippingNVP.='&PAYMENTREQUEST_0_SHIPPINGAMT='.number_format($this->shippingTotal, 2, '.', '');
		}
		else if($shipping===false)
		{
			$this->shippingNVP='&NOSHIPPING=1';
			$this->shippingTotal=0;
			$this->maxShipping=0;
		}
		else
		{
			$this->shippingNVP='&NOSHIPPING=1&PAYMENTREQUEST_0_SHIPPINGAMT='.number_format($shipping, 2, '.', '');
			$this->shippingTotal=$shipping;
			$this->maxShipping=$shipping;
		}
	}
	
	public function setTax($tax,$maxTax=0)
	{
		$this->taxNVP='&PAYMENTREQUEST_0_TAXAMT='.number_format($tax, 2, '.', '');
		$this->taxTotal=$tax;
		$this->maxTax=$maxTax<$tax?$tax:$maxTax;
	}

	/**
	 * Adapted from 'SetExpressCheckout NVP example; last modified 08MAY23.'
	 * Initiate an Express Checkout transaction.
	 * @return array|boolean array('token'=>$token,'url'=>$url on success, false on failure (error message available via getErrorMessage())
	*/
	public function setExpressCheckout($returnURL,$cancelURL,$items,$currencyID,$paymentType='Sale',$userCommit=true,$callbackURL=false)
	{
		$result=false;
		$nvpStr=
			'&RETURNURL='.urlencode($returnURL).
			'&CANCELURL='.urlencode($cancelURL).
			'&PAYMENTREQUEST_0_PAYMENTACTION='.urlencode($paymentType).
			'&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($currencyID);
		$i=0;
		$orderTotal=0.0;
		$maxAmt=$this->maxTax+$this->maxShipping;
		foreach($items as $item)
		{
			$price=$item['price'];
			$qty=$item['quantity'];
			$total=$price*$qty;
			$nvpStr.=
				'&L_PAYMENTREQUEST_0_NAME'.$i.'='.urlencode($item['name']).
				(isset($item['description'])?'&L_PAYMENTREQUEST_0_DESC'.$i.'='.urlencode($item['description']):'').
				'&L_PAYMENTREQUEST_0_AMT'.$i.'='.urlencode(number_format($price, 2, '.', '')).
				'&L_PAYMENTREQUEST_0_QTY'.$i.'='.urlencode($qty);
//				'&L_PAYMENTREQUEST_0_NUMBER'.$i.'='.urlencode($item['id']);
			$orderTotal+=$total;
			$i++;
		}
		$nvpStr.='&PAYMENTREQUEST_0_ITEMAMT='.number_format($orderTotal, 2, '.', '');
		$maxAmt+=$orderTotal;
		if($callbackURL!==false)
		{
			$nvpStr.='&CALLBACKTIMEOUT=6';
			$nvpStr.='&CALLBACK='.urlencode($callbackURL);
		}
		$nvpStr.='&PAYMENTREQUEST_0_AMT='.number_format($orderTotal+$this->shippingTotal+$this->taxTotal, 2, '.', '');
		$nvpStr.=$this->taxNVP.$this->shippingNVP;
		$nvpStr.='&MAXAMT='.number_format($maxAmt, 2, '.', '');
//var_dump($nvpStr);
		// Execute the API operation; see the PPHttpPost function above.
		$httpParsedResponseAr = $this->PPHttpPost('SetExpressCheckout', $nvpStr);
		if('SUCCESS' == strtoupper($httpParsedResponseAr['ACK']) || 'SUCCESSWITHWARNING' == strtoupper($httpParsedResponseAr['ACK']))
		{
			// Redirect to paypal.com.
//var_dump($httpParsedResponseAr);
			$token=urldecode($httpParsedResponseAr['TOKEN']);
			$result=array(
				'token'=>$token,
				'url'=>self::$paypalURLs[$this->environment].'&cmd=_express-checkout'.($userCommit?'&useraction=commit':'').'&token='.$token
			);
//			$result=urldecode($httpParsedResponseAr['TOKEN']);
//			header('Location: '.self::$paypalURLs[$this->environment].'&cmd=_express-checkout'.($userCommit?'&useraction=commit':'').'&token='.$result);
		}
		else
		{
			$this->errorMessage='SetExpressCheckout failed: ' . print_r($httpParsedResponseAr, true);
		}
		return $result;
	}

	/**
	 * Adapted from 'GetExpressCheckoutDetails NVP example; last modified 08MAY23' and 'DoExpressCheckoutPayment NVP example; last modified 08MAY23.'
	 * getExpressCheckoutDetails and doExpressCheckout rolled into one - must follow a call to setExpressCheckout.
	 * @param string $paymentType 'Authorization', 'Sale', or 'Order'
	 * @return array|boolean shipping address on success (street1, street2(possibly), city_name, state_province, postal_code, country_code), false on error (error message available via getErrorMessage())
	 */
	public function doExpressCheckout($paymentAmount,$currencyID,$paymentType='Sale')
	{
		$result=false;
		$payerID=false;
		$shipTo=array();
		/**
		 * This example assumes that this is the return URL in the SetExpressCheckout API call.
		 * The PayPal website redirects the user to this page with a token.
		 */
		 
		// Obtain the token from PayPal.
		$receivedToken = isset($_REQUEST['token'])?$_REQUEST['token']:false;
		if($receivedToken===false)
		{
			$this->errorMessage='Token is not received.';
		}
		else
		{
			// Add request-specific fields to the request string.
			$nvpStr = '&TOKEN='.urlencode(htmlspecialchars($receivedToken));
			// Execute the API operation; see the PPHttpPost function above.
			$httpParsedResponseAr = $this->PPHttpPost('GetExpressCheckoutDetails', $nvpStr);
			if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
				// Extract the response details.
				$payerID = $httpParsedResponseAr['PAYERID'];
/* Ignoring this for now...
				$this->shipTo['street1']=$httpParsedResponseAr["SHIPTOSTREET"];
				if(isset($httpParsedResponseAr['SHIPTOSTREET2']))
					$this->shipTo['street2']=$httpParsedResponseAr["SHIPTOSTREET2"];
				$this->shipTo['city_name'] = $httpParsedResponseAr["SHIPTOCITY"];
				$this->shipTo['state_province'] = $httpParsedResponseAr["SHIPTOSTATE"];
				$this->shipTo['postal_code'] = $httpParsedResponseAr["SHIPTOZIP"];
				$this->shipTo['country_code'] = $httpParsedResponseAr["SHIPTOCOUNTRYCODE"];
*/
			}
			else $this->errorMessage='GetExpressCheckoutDetails failed.';
		}
		
		if($payerID!==false)
		{
			// Add request-specific fields to the request string.
			$nvpStr=
				'&TOKEN='.urlencode($receivedToken).
				'&PAYERID='.urlencode($payerID).
				'&PAYMENTREQUEST_0_PAYMENTACTION='.urlencode($paymentType).
				'&PAYMENTREQUEST_0_AMT='.urlencode(number_format($paymentAmount,2,'.','')).
				'&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($currencyID);
			// Execute the API operation; see the PPHttpPost function above.
			$httpParsedResponseAr = $this->PPHttpPost('DoExpressCheckoutPayment', $nvpStr);
			if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
				$result=$httpParsedResponseAr;
			} else $this->errorMessage='DoExpressCheckoutPayment failed.';
		}
		return $result;
	}

	/**
	 * Send HTTP POST Request
	 *
	 * @param	string	$methodName The API method name
	 * @param	string	$nvpString The POST Message fields in &name=value pair format
	 * @return	array|boolean	Parsed HTTP Response body, or false on error (error message available via getErrorMessage())
	 */
	private function PPHttpPost($methodName, $nvpStr)
	{
		$result=false;
		$endpoint=self::$endpoints[$this->environment];
	 
		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
	 
//!!!!Don't do this for production code!!!! Turn off the server and peer verification (TrustManager Concept).
//	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
	 
		// Set the API operation, version, and API signature in the request.
		$nvpreq = 'METHOD='.$methodName.'&VERSION='.self::API_VERSION.'&PWD='.$this->password.'&USER='.$this->username.'&SIGNATURE='.$this->signature.$nvpStr;
//var_dump($nvpreq);
		// Set the request as a POST FIELD for curl.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	 
		// Get response from the server.
		$httpResponse = curl_exec($ch);
	 
		if(!$httpResponse)
		{
			$this->errorMessage=$methodName.' failed: '.curl_error($ch).'('.curl_errno($ch).')';
		}
		else
		{
			// Extract the response details.
			$httpParsedResponseAr=array();
			parse_str($httpResponse,&$httpParsedResponseAr);
			if((0 == sizeof($httpParsedResponseAr)) || !isset($httpParsedResponseAr['ACK']))
			{
				$this->errorMessage='Invalid HTTP Response for POST request to '.$endpoint;
			}
			else
			{
				$result=$httpParsedResponseAr;
			}
		}
		return $result;
	}
	private static function getShippingOptionsNVP($shippingOptions,&$maxAmt=0,&$shippingTotal=0)
	{
		$result='';
		$theMax=0;
		$i=0;
		if(count($shippingOptions>0)) $result.='&L_SHIPPINGOPTIONISDEFAULT0=true';
		foreach($shippingOptions as $name=>$amount)
		{
			if($i==0)
			{
				$shippingTotal=$amount;
			}
			if($amount>$theMax)$theMax=$amount;
			$result.='&L_SHIPPINGOPTIONNAME'.$i.'='.urlencode($name).'&L_SHIPPINGOPTIONAMOUNT'.$i.'='.number_format($amount, 2, '.', '');
			$i++;
		}
		$maxAmt+=$theMax;
		return $result;
	}
}
?>
