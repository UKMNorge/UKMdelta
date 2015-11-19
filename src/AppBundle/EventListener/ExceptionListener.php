<?php

#########
# This is the custom ExceptionHandler written for UKMDelta
# By Asgeir Stavik Hustad
# asgeirsh@ukmmedia.no
# UKM Norge / UKM Media
#########

#namespace Acme\CoreBundle\Listener;
namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener {
	
	// private $templateEngine;

	// public function __construct(EngineInterface $templateEngine) {
	// 	$this->templateEngine = $templateEngine;
	// }

	 /**
     * Handles a kernel exception and returns a relevant response.
     *
     * Aims to deliver content to the user that explains the exception, rather than falling
     * back on symfony's exception handler which displays a less verbose error message.
     *
     * @param GetResponseForExceptionEvent $event The exception event
     */
    public function onKernelException(GetResponseForExceptionEvent $event) 
    {
        // $response = $this->templateEngine->render(
        //     'TwigBundle:Exception:error500.html.twig',
        //     array('status_text' => $event->getException()->getMessage())
        // );

        $exception = $event->getException();

        // Sjekk hvilken exception det er her
        $message = 'Ooops, feil! Koden sier: ' . $exception->getMessage() . $exception->getCode();

        $response = new Response($message, Response::HTTP_OK);
        // $response->setContent($message);

        // HttpExceptionInterface is a special type of exception that
        // holds status code and header details
        // if ($exception instanceof HttpExceptionInterface) {
        //     $response->setStatusCode($exception->getStatusCode());
        //     $response->headers->replace($exception->getHeaders());
        // } else {
        //     $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        // }
        
        // $this->templateEngine->render('', $view_data);

        // Her sendes data til nettleseren.
        $event->setResponse($response);
    }
}
?>