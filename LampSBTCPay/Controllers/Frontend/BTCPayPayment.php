<?php
use Shopware\Components\CSRFWhitelistAware;

use LampSBTCPay\Components\BTCPayPayment\PaymentResponse;
use LampSBTCPay\Components\LampSBTCPay\BTCPayserverPaymentService;


class Shopware_Controllers_Frontend_BTCPayPayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    const PAYMENTSTATUSPAID = 12;

    protected static $api_endpoint_verify = '/api/shopware/verify';
    protected static $api_endpoint_create = '/api/shopware/create';
    static $file_priv="media/unknown/bitpay.pri";
    static $file_public="media/unknown/bitpay.pub";

    public function preDispatch()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSBTCPay'];
        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    public function getVersion(){
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSBTCPay'];
        $filename=$plugin->getPath().'/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */

        switch ($this->getPaymentShortName()) {
            case 'btcpay_payment':
                return $this->redirect(['action' => 'direct', 'forceSecure' => false]);
            case 'btcpay_payment_lbtc':
                return $this->redirect(['action' => 'direct', 'forceSecure' => false]);
            default:
                return $this->redirect(['controller' => 'checkout']);
        }
    }

    /**
     * Gateway action method.
     *
     * Collects the payment information and transmit it to the payment provider.
     */
    public function gatewayAction()
    {
        $paymentUrl = $this->getPaymentUrl();

        $this->redirect($paymentUrl);
    }

    /**
     * Direct action method.
     *
     * Collects the payment information and transmits it to the payment provider.
     */
    public function directAction()
    {
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        $paymentUrl = $this->getPaymentUrl();

        if(empty($paymentUrl) || filter_var($paymentUrl, FILTER_VALIDATE_URL)===false){
            $errorKey = 'CouldNotConnectToCryptoGate';
            $baseUrl = $this->Front()->Router()->assemble([
                'controller' => 'checkout',
                'action' => 'cart'
            ]);



            return $this->redirect(sprintf(
                '%s?%s=1',
                $baseUrl,
                $errorKey
            ));

        }


        $version = Shopware()->Config()->get( 'Version' );
        if($version < '5.6') {
            $connection = $this->container->get('dbal_connection');
            $user = $this->getUser();
            $basket=$this->getBasket();
            $sql = 'SELECT max(id) as max FROM s_order WHERE userID='.(int)$user["billingaddress"]["customer"]["id"];
            $data = $connection->fetchAll($sql, [':active' => true]);

            $this->saveOrder(
                sha1($data[0]["max"].$this->sessionToInteger($basket["content"][0]["sessionID"])),
                $service->createPaymentToken($this->getPaymentData())
            );
        }
        $this->redirect($paymentUrl);
    }

    public function returnAction(){

        /** @var BTCPayserverPaymentService\ $service */
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        /** @var \LampSBTCPay\Components\BTCPayPayment\Client $client */
        $client=$service->getClient();

        /** @var PaymentResponse $response */
        $response = $service->createPaymentResponse($this->Request());
        $token = $service->createPaymentToken($this->getPaymentData());



        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');
            return;
        }


        if (!$service->validatePayment($response)) {
            $this->forward('cancel');
            return;
        }



        $invoice = $client->getInvoiceByOrderId($response->token);
        $invoiceStatus = $invoice->getStatus();



        switch ($invoiceStatus) {
            case 'confirmed':
            case 'complete':
            case 'paid':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }

    }

    /**
     * Return action method
     *
     * Reads the transactionResult and represents it for the customer.
     */
    public function callbackAction()
    {

        /** @var BTCPayserverPaymentService\ $service */
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        /** @var \LampSBTCPay\Components\BTCPayPayment\Client $client */
        $client=$service->getClient();

        /** @var PaymentResponse $response */
        $response = $service->createPaymentResponse($this->Request());

        $invoice = $client->getInvoiceByOrderId($response->token);
        $invoiceStatus = $invoice->getStatus();

        $token = $service->createPaymentToken($this->getPaymentData());

        Shopware()->PluginLogger()->info("token:".$token." _ response:".$response->token);


        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');
            return;
        }

        if (!$service->validatePayment($response)) {
            $this->forward('cancel');
            return;
        }


        switch ($invoiceStatus) {
            case 'confirmed':
            case 'complete':
            case 'paid':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
    }

    /**
     * Creates the url parameters
     */
    private function getPaymentData()
    {


        $router = $this->Front()->Router();
        $user = $this->getUser();
        $basket=$this->getBasket();


        $billing = $user['billingaddress'];

        $version = Shopware()->Config()->get( 'Version' );
        $returnParameters = [
            'action' => 'return',
            'forceSecure' => true,
        ];
        $callbackParameters = [
            'action' => 'callback',
            'forceSecure' => true,
        ];
        if($version >= '5.6') {
            $shopware_token = $this->get('shopware\components\cart\paymenttokenservice')->generate();
            $returnParameters[\Shopware\Components\Cart\PaymentTokenService::TYPE_PAYMENT_TOKEN]=$shopware_token;
            $callbackParameters[\Shopware\Components\Cart\PaymentTokenService::TYPE_PAYMENT_TOKEN] = $shopware_token;
        }

        $connection = $this->container->get('dbal_connection');
        $sql = 'SELECT max(id) as max FROM s_order WHERE userID='.(int)$user["billingaddress"]["customer"]["id"];
        $data = $connection->fetchAll($sql, [':active' => true]);



        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'first_name' => $billing['firstname'],
            'last_name' => $billing['lastname'],
            'email' => @$user['additional']['user']['email'],
            'transaction_id' => sha1($data[0]["max"].$this->sessionToInteger($basket["content"][0]["sessionID"])),
            'return_url' => $router->assemble($returnParameters),
            'callback_url' => $router->assemble($callbackParameters),
            'cancel_url' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'seller_name' => Shopware()->Config()->get('company'),
            'memo' => 'Ihr Einkauf bei '.$_SERVER['SERVER_NAME'],
            'basket' => $basket["content"],
            'user_id' => $user["user"]["id"]
        ];

        switch ($this->getPaymentShortName()) {
            case 'btcpay_payment_lbtc':
                $parameter['selected_currencies'] = 'LBTC';
                break;
        }

        return $parameter;
    }

    private function sessionToInteger($sessionid){
        $int=0;
        for ( $pos=0; $pos < strlen($sessionid); $pos ++ ) {
            $byte = substr($sessionid, $pos);
            $int+=$byte;
        }
        return $int;
    }

    /**
     * Returns the URL of the payment provider. This has to be replaced with the real payment provider URL
     *
     * @return string
     */
    protected function getPaymentUrl()
    {
        /** @var BTCPayserverPaymentService $service */
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        $payment_url = $service->createPaymentUrl($this->getPaymentData(),$this->getVersion());
        return $payment_url;
    }

    public function getWhitelistedCSRFActions() {
        return [
            'return','callback','cancel',
        ];
    }



}
