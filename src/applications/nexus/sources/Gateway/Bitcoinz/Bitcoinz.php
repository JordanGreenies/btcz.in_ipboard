<?php

namespace IPS\nexus\Gateway;

/**
 * @brief        Bitcoinz Gateway
 * @author       BTCz.in 
 * @copyright    (c) 2018 BTCz.in
 * @license      MIT
 * @package      IPS Social Suite
 * @subpackage   Nexus
 * @version      1.0.0
 */

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Bitcoinz Gateway
 */
class _Bitcoinz extends \IPS\nexus\Gateway
{
    /* !Features (Each gateway will override) */

    const SUPPORTS_REFUNDS = false;
    const SUPPORTS_PARTIAL_REFUNDS = false;
    const DEFAULT_PINGBACK_RESPONSE = 'OK';

    /**
     * Can store cards?
     *
     * @return    bool
     */
    public function canStoreCards()
    {
        return FALSE;
    }

    /**
     * Admin can manually charge using this gateway?
     *
     * @return    bool
     */
    public function canAdminCharge()
    {
        $settings = json_decode($this->settings, TRUE);
        return ($settings['method'] === 'direct');
    }

    public function CreateGateway($transaction, $MerchantAddress, $PingbackUrl, $MerchantEmail, $InvoiceID, $Amount, $Expire, $Secret, $CurrencyCode)
    {
        $APIUrl = 'https://btcz.in/api/process';
	
        $fields = array(
                'f' => "create",
                'p_addr' => urlencode($MerchantAddress),
                'p_pingback' => urlencode($PingbackUrl),
                'p_invoicename' => urlencode($InvoiceID),
                'p_email' => urlencode($MerchantEmail),
                'p_secret' => urlencode($Secret),
                'p_expire' => urlencode($Expire),
                'p_success_url' => (string)\IPS\Http\Url::internal(
                    'app=nexus&module=clients&controller=invoices&id=' . $transaction->invoice->id,
                    'front',
                    'clientsinvoice',
                    array(),
                    \IPS\Settings::i()->nexus_https
                ),
		'p_currency_code' => urlencode($CurrencyCode),
		'p_amount' => urlencode($Amount)			
        );
		
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $APIUrl);
        curl_setopt($ch,CURLOPT_POST, count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
			
        $result = curl_exec($ch);
        $response = curl_getinfo( $ch );
        curl_close($ch);
		
        if($response['http_code'] != 200)
            return false;
		
        return $result;
    }

    /**
     * Authorize
     *
     * @param    \IPS\nexus\Transaction $transaction Transaction
     * @param    array|\IPS\nexus\Customer\CreditCard $values Values from form OR a stored card object if this gateway supports them
     * @param    \IPS\nexus\Fraud\MaxMind\Request|NULL $maxMind *If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made
     * @return    \IPS\DateTime|NULL        Auth is valid until or NULL to indicate auth is good forever
     * @throws    \LogicException            Message will be displayed to user
     */
    public function auth(\IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL)
    {
        // Change order status to Waiting
        // Notice: When change status to PENDING, the site appears some errors about the template
        //         Call to undefined method IPS\Theme\class_nexus_admin_transactions::pend()
        $transaction->status = \IPS\nexus\Transaction::STATUS_WAITING;
        $extra = $transaction->extra;
        $extra['history'][] = array('s' => \IPS\nexus\Transaction::STATUS_WAITING);
        $transaction->extra = $extra;
        $transaction->save();

        // In case: When a guest checks out they will be prompted to create an account,
        //          The account will not be created until payment has been authorised
        // So, we create a new account to get member_id before create the widget
        // After checkout, the customer needs re-login to check order status
        $this->registerMember($transaction->invoice);

        $settings = $this->getSettings();

	$RESP =  $this->CreateGateway($transaction, $settings['address'], $settings['pingbackurl'], $settings['email'], $transaction->id, $transaction->amount->amount, 15, $settings['secret_key'], $transaction->currency);
	$JSON_RESP = json_decode($RESP);

	if(!empty($JSON_RESP))
	{
		$InvoiceURL = "https://btcz.in/invoice?id=".$JSON_RESP->url_id;
		echo '<iframe id="iFrame" style="min-height: 725px" width="100%"  frameborder="0" src="'.$InvoiceURL.'" scrolling="no" onload="resizeIframe()"></iframe>';
		echo "<script type=\"text/javascript\">
		function resizeIframe() {
			var obj = document.getElementById(\"iFrame\");
			obj.style.height = (obj.contentWindow.document.body.scrollHeight) + 'px';
			setTimeout('resizeIframe()', 200);
		}
		</script>";
	}
	else if(strlen($RESP))
	{
		echo $RESP; //Printable error
	}
	else
	{
		echo "Error: No response from API"; //Unknown error
	}
	    
        die;
    }

    /**
     * Capture
     *
     * @param    \IPS\nexus\Transaction $transaction Transaction
     * @return bool
     */
    public function capture(\IPS\nexus\Transaction $transaction)
    {
        return true;
    }

    /**
     * Refund
     *
     * @param    \IPS\nexus\Transaction $transaction Transaction to be refunded
     * @param    float|NULL $amount Amount to refund (NULL for full amount - always in same currency as transaction)
     * @return    mixed  Gateway reference ID for refund, if applicable
     * @throws    \Exception
     */
    public function refund(\IPS\nexus\Transaction $transaction, $amount = NULL)
    {
        return isset($_GET['ref']) ? $_GET['ref'] : null;
    }


    public function paymentScreen(\IPS\nexus\Invoice $invoice, \IPS\nexus\Money $amount)
    {
        return array();
    }

    public function handlerPingback(\IPS\nexus\Transaction $transaction)
    {
        $settings = $this->getSettings();	
	$Pingback_IP = $this->getRealClientIP();
	    
	if($Pingback_IP != "164.132.164.206")
		die("Invalid pingback IP");	
	    
	$data = json_decode($_POST["data"]);		
	if($data->secret != $settings['secret_key']) //unknown secret
		die("Invalid secret key");
	    
	$invoice = $transaction->get_invoice();
	    
	 if($data->state == 5) //success
	 {
		$transaction->approve();
	 } 
	 else {
		if ($invoice->status == \IPS\nexus\Invoice::STATUS_PAID) {
			$transaction->refund();
			// Update invoice status
			$invoice->markUnpaid(\IPS\nexus\Invoice::STATUS_CANCELED);
		}
	 }
		
	return self::DEFAULT_PINGBACK_RESPONSE;
    }

    public function getRealClientIP()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = $_SERVER;
        }

        //Get the forwarded IP if it exists
        if (array_key_exists('X-Forwarded-For', $headers)
            && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $the_ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers)
            && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
        } elseif(array_key_exists('Cf-Connecting-Ip', $headers)) {
            $the_ip = $headers['Cf-Connecting-Ip'];
        } else {
            $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }
        return $the_ip;
    }

    /* !ACP Configuration */

    /**
     * Settings
     *
     * @param    \IPS\Helpers\Form $form The form
     * @return    void
     */
    public function settings(&$form)
    {
        $settings = json_decode($this->settings, TRUE);

		$form->add(new \IPS\Helpers\Form\Text('bitcoinz_address', $settings['address'], TRUE));
        $form->add(new \IPS\Helpers\Form\Text('bitcoinz_secret_key', $settings['secret_key'], FALSE));
		$form->add(new \IPS\Helpers\Form\Text('bitcoinz_pingbackurl', $settings['pingbackurl'], TRUE));
		$form->add(new \IPS\Helpers\Form\Text('bitcoinz_email', $settings['email'], TRUE));
    }

    /**
     * Test Settings
     *
     * @param    array $settings Settings
     * @return    array
     * @throws    \InvalidArgumentException
     */
    public function testSettings($settings)
    {
        if (trim($settings['address']) == '') {
            throw new \LogicException('BTCz address is required');
        }
        if (trim($settings['pingbackurl']) == '') {
            throw new \LogicException('Pingback URL is required');
        }
        if (trim($settings['email']) == '') {
            throw new \LogicException('Email is required');
        }
				
        return $settings;
    }

    /**
     * @param \IPS\nexus\Transaction $transaction
     * @return array
     */
    private function prepareUserProfileData(\IPS\nexus\Transaction $transaction)
    {
        $billingAddress = $transaction->invoice->billaddress;
        $billingData = array();
        $member = $transaction->member;

        if ($billingAddress) {
            $billingData = array(
                'customer[city]' => $billingAddress->city,
                'customer[state]' => $billingAddress->region,
                'customer[address]' => implode("\n", $billingAddress->addressLines),
                'customer[country]' => $billingAddress->country,
                'customer[zip]' => $billingAddress->postalCode,
            );
        }

        return array_merge(
            array(
                'customer[username]' => $member->name,
                'customer[firstname]' => $member->cm_first_name,
                'customer[lastname]' => $member->cm_last_name,
                'history[membership]' => $member->member_group_id,
                'history[registration_date]' => $member->joined->getTimestamp(),
                'history[registration_email]' => $member->email,
                'history[registration_age]' => $member->age(),
            ),
            $billingData
        );
    }

    /**
     * @param \IPS\nexus\Transaction $transaction
     * @param bool $isTest
     * @return array
     */
    private function prepareDeleveryData(\IPS\nexus\Transaction $transaction, $isTest = false, $ref)
    {
        $shippingAddress = $transaction->invoice->shipaddress;
        $shippingData = array();

        if ($shippingAddress) {
            $shippingData = array(
                'shipping_address[country]' => $shippingAddress->country,
                'shipping_address[city]' => $shippingAddress->city,
                'shipping_address[zip]' => $shippingAddress->postalCode,
                'shipping_address[state]' => $shippingAddress->region,
                'shipping_address[street]' => implode("\n", $shippingAddress->addressLines),
            );
        }

        return array_merge(
            array(
                'payment_id' => $ref,
                'type' => 'digital',
                'status' => 'delivered',
                'estimated_delivery_datetime' => date('Y/m/d H:i:s'),
                'estimated_update_datetime' => date('Y/m/d H:i:s'),
                'is_test' => $isTest,
                'reason' => 'none',
                'refundable' => 'yes',
                'details' => 'Item will be delivered via email by ' . date('Y/m/d H:i:s'),
                'shipping_address[email]' => $transaction->member->email,
                'shipping_address[firstname]' => $transaction->member->cm_first_name,
                'shipping_address[lastname]' => $transaction->member->cm_last_name,
            ),
            $shippingData
        );
    }

    /**
     * @return mixed
     */
    public function getSettings()
    {
        return json_decode($this->settings, true);
    }

    /**
     * @param \IPS\nexus\Invoice $invoice
     */
    private function registerMember(\IPS\nexus\Invoice &$invoice)
    {
        // Create the member account if this was a guest
        if (!$invoice->member->member_id and $invoice->guest_data) {
            $profileFields = $invoice->guest_data['profileFields'];

            $memberToSave = new \IPS\nexus\Customer;
            foreach ($invoice->guest_data['member'] as $k => $v) {
                $memberToSave->_data[$k] = $v;
                $memberToSave->changed[$k] = $v;
            }
            $memberToSave->save();
            $invoice->member = $memberToSave;
            $invoice->guest_data = NULL;
            $invoice->save();

            // If we've entered an address during checkout, save it
            if ($invoice->billaddress !== NULL) {
                $billing = new \IPS\nexus\Customer\Address;
                $billing->member = $invoice->member;
                $billing->address = $invoice->billaddress;
                $billing->primary_billing = 1;
                $billing->save();
            }

            if ($this->shipaddress !== NULL) {
                $shipping = new \IPS\nexus\Customer\Address;
                $shipping->member = $invoice->member;
                $shipping->address = $invoice->shipaddress;
                $shipping->primary_shipping = 1;
                $shipping->save();
            }

            $profileFields['member_id'] = $memberToSave->member_id;
            \IPS\Db::i()->replace('core_pfields_content', $profileFields);

            // Notify the incoming mail address
            if (\IPS\Settings::i()->new_reg_notify) {
                \IPS\Email::buildFromTemplate('core', 'registration_notify', array($memberToSave, $profileFields))->send(\IPS\Settings::i()->email_in);
            }

            // Update associated transactions
            \IPS\Db::i()->update('nexus_transactions', array('t_member' => $invoice->member->member_id), array('t_invoice=? AND t_member=0', $invoice->id));
        }
    }
}
