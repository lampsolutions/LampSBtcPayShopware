<?php

use LampSBTCPay\Components\BTCPayPayment\PaymentResponse;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;

class Shopware_Controllers_Backend_BTCPayPairing extends \Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware {

    static $file_priv="media/unknown/bitpay.pri";
    static $file_public="media/unknown/bitpay.pub";

    public function preDispatch()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/../../Resources/views/');
    }

    public function indexAction(){
        require __DIR__.'/../../vendor/autoload.php';

        $storageEngine = new \Bitpay\Storage\ShopwareEncryptedFilesystemStorage(Shopware()->Config()->getByNamespace('LampSBTCPay', 'SECRET'));


        $api_url = Shopware()->Config()->getByNamespace('LampSBTCPay', 'api_url');
        $this->View()->assign(['api_url' => $api_url]);


        $container=Shopware()->Container();
        $mediaService = $container->get('shopware_media.media_service');
        $fileExists = $mediaService->has(self::$file_priv);
        if(!$fileExists){
            $privateKey = \Bitpay\PrivateKey::create(self::$file_priv)->generate();
            $publicKey = new \Bitpay\PublicKey(self::$file_public);
            $publicKey->setPrivateKey($privateKey);
            $publicKey->generate();
            $storageEngine->persist($privateKey);
            $storageEngine->persist($publicKey);
        }


        if($_POST["pair_now"]=="pair_now"){
            try{
                $storageEngine->load(self::$file_priv);
            }
            catch (Exception $e){
                $privateKey = \Bitpay\PrivateKey::create(self::$file_priv)->generate();
                $publicKey = new \Bitpay\PublicKey(self::$file_public);
                $publicKey->setPrivateKey($privateKey);
                $publicKey->generate();
                $storageEngine->persist($privateKey);
                $storageEngine->persist($publicKey);
            }
            $privateKey    = $storageEngine->load(self::$file_priv);
            $publicKey     = $storageEngine->load(self::$file_public);
            $client = new \Bitpay\Client\Client();

            $adapter = new \Bitpay\Client\Adapter\CurlAdapter();
            $client->setPrivateKey($privateKey);
            $client->setPublicKey($publicKey);
            $client->setUri($api_url);

            $client->setAdapter($adapter);

            $pairingCode = $_POST["ParingCode"];
            $sin = \Bitpay\SinKey::create()->setPublicKey($publicKey)->generate();
            try {
                $token = $client->createToken(
                    array(
                        'pairingCode' => $pairingCode,
                        'label'       => 'testtoken',
                        'id'          => (string) $sin,
                    )
                );
                $this->View()->assign(['token' => $token]);


            } catch (\Exception $e) {


                $request  = $client->getRequest();
                $response = $client->getResponse();

                $this->View()->assign(['error' => "Paring not successfull. See Plugin log for more Information"]);
                Shopware()->PluginLogger()->error("BTCPay-Payment-Error: ".$e->getMessage());



            }


        }
    }

    public function testAction()
    {
        $service = $this->container->get('btc_pay.btc_pay_payment_service');

        if ($_GET["apiToken"]) {
            $service->setOverrideToken($_GET["apiToken"]);
        }
        if ($_GET["apiUrl"]) {
            $service->setOverrideUrl(urldecode($_GET["apiUrl"]));
        }

        $paymentUrl = $this->getPaymentUrl();


        if(false===$paymentUrl || filter_var($paymentUrl, FILTER_VALIDATE_URL)===false){
            header("HTTP/1.0 200 Not Okay");
            $result = "Could not generate Payment-URL. Maybe you need to <a href='#' onclick=\"Shopware.ModuleManager.createSimplifiedModule('BTCPayPairing', { 'title': 'BTCPay Pairing' }); return false;\">pair</a> your server first. Please see logfile for possible Exceptions";
        }
        else {

            /** @var PaymentResponse $response */
            $response = new \LampSBTCPay\Components\BTCPayPayment\PaymentResponse();
            $response->transactionId = end(explode("/", $paymentUrl));
            $response->token = $service->createPaymentToken($this->getPaymentData());


            $result['response']='Success!';
            echo json_encode($result);
            die();

        }

        echo $result;
        die();
    }

    /**
     * Creates the url parameters
     */
    private function getPaymentData()
    {

        $parameter = [
            'amount' => 1.00,
            'currency' => "EUR",
            'first_name' => "first_name",
            'last_name' => "last_name",
            'payment_id' => 42,
            'email' => "test@example.com",
            'return_url' => "__not_set__",
            'callback_url' => "__not_set__",
            'cancel_url' => "__not_set__",
            'seller_name' => Shopware()->Config()->get('company'),
            'memo' => ''.$_SERVER['SERVER_NAME']
        ];
        return $parameter;

    }


    protected function getPaymentUrl()
    {
        /** @var BTCPayPaymentService $service */
        $service = $this->container->get('btc_pay.btc_pay_payment_service');
        $payment_url = $service->createPaymentUrl($this->getPaymentData(),$this->getVersion());
        return $payment_url;
    }

    public function getVersion(){
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSBTCPay'];
        $filename=$plugin->getPath().'/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    public function getWhitelistedCSRFActions() {
        return [
            'index', 'test',
        ];
    }
}