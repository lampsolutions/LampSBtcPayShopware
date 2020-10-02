<?php

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

                $shop          = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->findOneBy(array('default' => true));
                $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
                $plugin        = $pluginManager->getPluginByName('LampSBTCPay');
                $pluginManager->saveConfigElement($plugin, 'api_token', $token->getToken(), $shop);

            } catch (\Exception $e) {


                $request  = $client->getRequest();
                $response = $client->getResponse();

                $this->View()->assign(['error' => $e->getMessage()]);

                $this->View()->assign(['request' => $request]);
                $this->View()->assign(['request' => $response]);

            }


        }
    }

    public function getWhitelistedCSRFActions() {
        return [
            'index',
        ];
    }
}