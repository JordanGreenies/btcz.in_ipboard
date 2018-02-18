<?php
/**
 * @brief        btczPingback
 * @author       BTCz.in
 * @copyright    (c) 2015 BTCz.in
 * @subpackage    Nexus
 */

namespace IPS\nexus\modules\front\checkout;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * ipn
 */
class _btczPingback extends \IPS\Dispatcher\Controller
{

    /**
     * Process Pingback
     *
     * @return    void
     */
    protected function manage()
    {
        try {
			if(!isset($_POST["data"]))
				die('No data!');
			$data = json_decode($_POST["data"]);
			if(empty($data))
				die('No data!');
			
            $transaction = \IPS\nexus\Transaction::load($data->invoicename);
        } catch (\OutOfRangeException $e) {
            die('Transaction invalid!');
        }

        try {
            $response = $transaction->method->handlerPingback($transaction);
            die($response);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }
}