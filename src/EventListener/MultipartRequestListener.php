<?php

namespace Goetas\MultipartUploadBundle\EventListener;

use Goetas\MultipartUploadBundle\Service\MultipartRequestHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class MultipartRequestListener
{
    /**
     * @var MultipartRequestHandler
     */
    private $requestHandler;

    public function __construct(MultipartRequestHandler $requestHandler)
    {
        $this->requestHandler = $requestHandler;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        try {
            $this->requestHandler->processRequest($event->getRequest());
        } catch (\LogicException $e) {
            $message = 'Bad Request';

            if ($e->getMessage()) {
                $message .= ': ' . $e->getMessage();
            }

            $response = new Response($message, Response::HTTP_BAD_REQUEST);

            $event->setResponse($response);
        }
    }
}
