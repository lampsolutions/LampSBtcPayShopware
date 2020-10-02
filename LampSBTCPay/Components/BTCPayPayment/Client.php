<?php


namespace LampSBTCPay\Components\BTCPayPayment;


use Bitpay\Client\Request;

class Client extends \Bitpay\Client\Client{
    /**
     * @inheritdoc
     */
    public function getInvoiceByOrderId($orderId){

        $this->request = $this->createNewRequest();
        $this->request->setMethod(Request::METHOD_GET);
        if ($this->token && $this->token->getFacade() === 'merchant') {
            $this->request->setPath(sprintf('invoices?token=%s&orderId=', $this->token->getToken(),$orderId));
            $this->addIdentityHeader($this->request);
            $this->addSignatureHeader($this->request);
        }
        $this->response = $this->sendRequest($this->request);
        $body = json_decode($this->response->getBody(), true);

        if (isset($body['error'])) {
            throw new \Exception($body['error']);
        }

        $data = $body['data'];


        $invoice = new \Bitpay\Invoice();
        $invoice = $this->fillInvoiceData($invoice, $data[0]);

        return $invoice;
    }


}