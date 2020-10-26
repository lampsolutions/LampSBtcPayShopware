<?php

namespace LampSBTCPay\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\CacheManager;
use Shopware_Controllers_Backend_Config;

class CacheSubscriber implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * SomeSubscriber constructor.
     */
    public function __construct($pluginName, $cacheManager)
    {
        $this->pluginName = $pluginName;
        $this->cacheManager = $cacheManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Config' => 'onPostDispatchConfig'
        ];
    }

    public function onPostDispatchConfig(\Enlight_Event_EventArgs $args)
    {
        /** @var Shopware_Controllers_Backend_Config $subject */
        $subject = $args->get('subject');
        $request = $subject->Request();


        // If this is a POST-Request, and affects our plugin, we may clear the config cache
        if($request->isPost() && $request->getParam('name') === end(explode("/",$this->pluginName))) {

            $this->cacheManager->clearByTag(CacheManager::CACHE_TAG_CONFIG);
        }
    }

}