<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License 
 * see https://github.com/bitpay/php-bitpay-client/blob/master/LICENSE
 */

namespace Bitpay\Storage;

/**
 */
class ShopwareEncryptedFilesystemStorage implements StorageInterface
{
    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $unencoded_password;

    /**
     * Initialization Vector
     */
    const IV = '0000000000000000';

    /**
     * @var string
     */
    const METHOD = 'AES-128-CBC';

    /**
     * @var int
     */
    const OPENSSL_RAW_DATA = 1;

    /**
     * @param string $password
     */
    public function __construct($password)
    {
        //to make this an non-breaking api change,
        //I will have to keep both versions of the password
        $this->password = base64_encode($password);
        $this->unencoded_password = $password;
        $container=Shopware()->Container();
        $this->mediaService = $container->get('shopware_media.media_service');
    }

    /**
     * @inheritdoc
     */
    public function persist(\Bitpay\KeyInterface $key)
    {
        $path    = $key->getId();
        $data    = serialize($key);
        $encoded = bin2hex(openssl_encrypt(
            $data,
            self::METHOD,
            $this->password,
            1,
            self::IV
        ));
        $this->mediaService->write($path, $encoded);
    }

    /**
     * @inheritdoc
     */
    public function load($id)
    {
        $encoded = $this->mediaService->read($id);
        $decoded = openssl_decrypt(\Bitpay\Util\Util::binConv($encoded), self::METHOD, $this->password, 1, self::IV);

        if (false === $decoded) {
            $decoded = openssl_decrypt(\Bitpay\Util\Util::binConv($encoded), self::METHOD, $this->unencoded_password, 1, self::IV);
        }

        if (false === $decoded) {
            throw new \Exception('Could not decode key');
        }

        return unserialize($decoded);
    }
}
