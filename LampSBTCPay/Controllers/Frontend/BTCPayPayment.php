<?php
use Shopware\Components\CSRFWhitelistAware;

use LampSBTCPay\Components\BTCPayPayment\PaymentResponse;
use LampSBTCPay\Components\CryptoGatePayment\BTCPayserverPaymentService;


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


        $version = Shopware()->Config()->get( 'Version' );
        if($version < '5.6') {
            $basket=$this->getBasket();
            $this->saveOrder(
                sha1($this->sessionToInteger($basket["content"][0]["sessionID"])),
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

        $invoice = $client->getInvoiceByOrderId($response->token);
        $invoiceStatus = $invoice->getStatus();


        switch ($invoiceStatus) {
            case 'confirmed':
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

        require __DIR__.'/../../vendor/autoload.php';

        $raw_post_data = file_get_contents('php://input');

        $ipn = json_decode($raw_post_data);

        /** @var BTCPayserverPaymentService\ $service */
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        $client=$service->getClient();


        $invoice = $client->getInvoice($ipn->id);
        $invoiceId = $invoice->getId();
        $invoiceStatus = $invoice->getStatus();
        $orderId=$invoice->getOrderId();


        switch ($invoiceStatus) {
            case 'paid':
                $this->saveOrderFromCallBack(
                    "invoice?.$invoiceId",
                    $service->createPaymentToken($orderId),
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
        if($version >= '5.6') {
            $shopware_token = $this->get('shopware\components\cart\paymenttokenservice')->generate();
            $returnParameters[\Shopware\Components\Cart\PaymentTokenService::TYPE_PAYMENT_TOKEN]=$shopware_token;
        }


        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'first_name' => $billing['firstname'],
            'last_name' => $billing['lastname'],
            'email' => @$user['additional']['user']['email'],
            'transaction_id' => sha1($this->sessionToInteger($basket["content"][0]["sessionID"])),
            'return_url' => $router->assemble($returnParameters),
            'callback_url' => $router->assemble(['action' => 'callback', 'forceSecure' => true]),
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
            'return',
        ];
    }

    public function saveOrderFromCallBack($transactionId, $paymentUniqueId,$paymentStatusId = null)
    {





        if (empty($orderNumber)) {
           // $user = $this->getUser();
          //  $basket = $this->getBasket();

            $order = Shopware()->Modules()->Order();
            $order->sUserData = $user;
            $order->sComment = "";
            $order->sBasketData = $basket;
            $order->sAmount = $basket['sAmount'];
            $order->sAmountWithTax = !empty($basket['AmountWithTaxNumeric']) ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];
            $order->sAmountNet = $basket['AmountNetNumeric'];
            $order->sShippingcosts = $basket['sShippingcosts'];
            $order->sShippingcostsNumeric = $basket['sShippingcostsWithTax'];
            $order->sShippingcostsNumericNet = $basket['sShippingcostsNet'];
            $order->bookingId = $transactionId;
            $order->dispatchId = Shopware()->Session()->sDispatch;
            $order->sNet = empty($user['additional']['charge_vat']);
            $order->uniqueID = $paymentUniqueId;
            $order->deviceType = $this->Request()->getDeviceType();
            $orderNumber = $order->sSaveOrder();
        }

        if (!empty($orderNumber) && !empty($paymentStatusId)) {
            $this->savePaymentStatus($transactionId, $paymentUniqueId, $paymentStatusId, false);
        }

        return $orderNumber;
    }



}
