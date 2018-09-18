<?php

declare(strict_types=1);

namespace Valiton\Payum\Payone\Action\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\RenderTemplate;
use Valiton\Payum\Payone\Api;
use Valiton\Payum\Payone\Request\Api\GetBankAccountData;

class GetBankAccountDataAction extends BaseApiAwareAction implements GatewayAwareInterface
{
    /**
     * @var GatewayInterface
     */
    private $gateway;

    /**
     * @var string
     */
    private $templateName;

    /**
     * @var ArrayObject
     */
    private $options;

    /**
     * @param string $templateName
     */
    public function __construct($templateName, array $options)
    {
        parent::__construct();

        $this->templateName = $templateName;
        $this->options = ArrayObject::ensureArrayObject($options);
    }

    /**
     * @param mixed $request
     *
     * @throws \Payum\Core\Exception\RequestNotSupportedException if the action dose not support the request.
     */
    public function execute($request)
    {
        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (null !== $model->get(Api::FIELD_IBAN, null)) {
            // we already have a iban
            return;
        }

        // process form submission if present
        $this->gateway->execute($httpRequest = new GetHttpRequest());
        if ('POST' === $httpRequest->method) {
            $postParams = [];
            parse_str($httpRequest->content, $postParams);
            if (array_key_exists(Api::FIELD_IBAN, $postParams)) {
                $model[Api::FIELD_IBAN] = $postParams[Api::FIELD_IBAN];
                return;
            }
        }

        $params = [
            'aid' => $this->options['sub_account_id'],
            'encoding' => 'UTF-8',
            'mid' => $this->options['merchant_id'],
            'mode' => $this->options['sandbox'] ? 'test' : 'live',
            'portalid' => $this->options['portal_id'],
            'request' => 'bankaccountcheck',
            'responsetype' => 'JSON',
        ];
        ksort($params);
        $hash = hash('md5', implode('', $params) . $this->options['key']);

        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, [
            'params' => $params,
            'hash' => $hash,
            'language' => 'de',
            'actionUrl' => $request->getToken() ? $request->getToken()->getTargetUrl() : null,
        ]));

        throw new HttpResponse($renderTemplate->getResult());
    }

    /**
     * @param mixed $request
     *
     * @return boolean
     */
    public function supports($request)
    {
        return
            $request instanceof GetBankAccountData &&
            $request->getModel() instanceof \ArrayAccess;
    }

    /**
     * @param \Payum\Core\GatewayInterface $gateway
     */
    public function setGateway(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }
}
