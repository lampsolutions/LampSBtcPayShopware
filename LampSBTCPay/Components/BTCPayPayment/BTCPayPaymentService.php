<?php

namespace LampSBTCPay\Components\BTCPayPayment;


use Bitpay\Client\Request;
use GuzzleHttp\Exception\RequestException;

class BTCPayPaymentService
{
    protected static $api_endpoint_verify = '/api/shopware/verify';
    protected static $api_endpoint_create = '/api/shopware/create';

    static $file_priv="media/unknown/bitpay.pri";
    static $file_public="media/unknown/bitpay.pub";

    private $overrideUrl=false;
    private $overrideToken=false;

    /**
     * @param $overrideUrl
     */
    public function setOverrideUrl($overrideUrl)
    {
        $this->overrideUrl = $overrideUrl;
    }

    /**
     * @param $overrideToken
     */
    public function setOverrideToken($overrideToken)
    {
        $this->overrideToken = $overrideToken;
    }

    /**
     * @param $request \Enlight_Controller_Request_Request
     * @return PaymentResponse
     */
    public function createPaymentResponse(\Enlight_Controller_Request_Request $request)
    {
        $response = new PaymentResponse();
        $response->transactionId = $request->getParam('transactionId', null);
        $response->status = $request->getParam('status', null);
        $response->token = $request->getParam('token', null);

        return $response;
    }

    /**
     * @param PaymentResponse $response
     * @param string $token
     * @return bool
     */
    public function isValidToken(PaymentResponse $response, $token)
    {
        return hash_equals($token, $response->token);
    }

    /**
     * @param array $payment_data
     * @return string
     */
    public function createPaymentToken($payment_data)
    {
        unset($payment_data["return_url"]);
        unset($payment_data["callback_url"]);
        unset($payment_data["transaction_id"]);

        return sha1(implode('|', $payment_data));
    }

    public function getClient(){
        require __DIR__.'/../../vendor/autoload.php';

        $api_url = Shopware()->Config()->getByNamespace('LampSBTCPay', 'api_url');
        if($this->overrideUrl){
            $api_url=$this->overrideUrl;
        }

        if(empty($api_url)){
            Shopware()->PluginLogger()->error("BTCPay-Payment-Error: Missing API Url");
            return false;
        }
        $api_key = Shopware()->Config()->getByNamespace('LampSBTCPay', 'api_token');
        if($this->overrideToken){
            $api_key=$this->overrideToken;
        }
        if(empty($api_key)){
            Shopware()->PluginLogger()->error("BTCPay-Payment-Error: Missing API Kay");
            return false;
        }

        $storageEngine = new \Bitpay\Storage\ShopwareEncryptedFilesystemStorage(Shopware()->Config()->getByNamespace('LampSBTCPay', 'SECRET'));
        $privateKey    = $storageEngine->load(self::$file_priv);
        $publicKey     = $storageEngine->load(self::$file_public);
        $client = new Client();

        $client->setPrivateKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setUri($api_url);

        $token = new \Bitpay\Token();
        $token->setToken($api_key);


        $client->setToken($token);

        $token->setFacade('merchant');


        return $client;
    }

    public function createPaymentUrl($parameters=array(),$version) {

        try{
            $client=$this->getClient();
        }
        catch (\Exception $e){
            Shopware()->PluginLogger()->warn("BTCPay-Payment-Error:".$e->getMessage());
            return null;
        }

        $invoice = new \Bitpay\Invoice();

        /** @var \Bitpay\User $buyer */
        $buyer = new \Bitpay\Buyer();
        if(Shopware()->Config()->getByNamespace('LampSBTCPay', 'transmit_customer_data')!==false) {
            $buyer
                ->setEmail($parameters["email"])
                ->setFirstName($parameters["first_name"])
                ->setLastName($parameters["last_name"]);
        }

        // Add the buyers info to invoice
        $invoice->setBuyer($buyer);

        if(count($parameters["basket"])> 1){
            $parameters["basket"][0]["articlename"]=$parameters["basket"][0]["articlename"]."( 1 of ".count($parameters["basket"])." items )";
        }

        /**
         * Item is used to keep track of a few things
         */
        $item = new \Bitpay\Item();
        $item
            ->setCode($parameters["basket"][0]["ordernumber"])
            ->setDescription($parameters["basket"][0]["articlename"])
            ->setPrice($parameters["amount"]);
        $invoice->setItem($item);

        $invoice->setCurrency(new \Bitpay\Currency('EUR'));

        // Configure the rest of the invoice
        $invoice
            ->setOrderId($this->createPaymentToken($parameters))
            ->setNotificationUrl($parameters["callback_url"]."?token=".
                $this->createPaymentToken($parameters)."&transactionId=".
                $parameters["transaction_id"])
            ->setRedirectUrl($parameters["return_url"]."?token=".
                $this->createPaymentToken($parameters)."&transactionId=".
                $parameters["transaction_id"]);
        try{
            $client->createInvoice($invoice);
        }
        catch (\Exception $e){
            Shopware()->PluginLogger()->warn("BTCPay-Payment-Error:".$e->getMessage());
        }


        return $invoice->getUrl();
    }



    public function validatePayment(PaymentResponse $paymentResponse) {

        return true;
    }


}
