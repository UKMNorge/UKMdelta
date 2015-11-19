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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Templating;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use Symfony\Component\Templating\PhpEngine;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Twig_Environment;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;

class ExceptionListener {
	
	// protected $templating;

	public function __construct($container) {
		// echo 'Constructing... ';
		//var_dump($container);
		$this->container = $container;
	}

	// function __construct($container) {
	//     $this->container = $container;
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

    	// echo 'Exception caught! ';
        $exception = $event->getException();

        // echo $exception->getCode() . '<br>';
        // echo $exception->getMessage().'<br>';
        // die();
        $response = new Response();
        $view_data = array();
        // Sjekk hvilken exception det er her
        // TODO: Logg stuff her
        switch ($exception->getCode()) {
        	case 0:
        		// Egne exceptions uten statuskode dukker opp her!'
        		// echo 'deltaException:<br>';
        		$view_data = $this->deltaException($event);
        		break;
        	case 404:
        		// Not found Exception
        		$view_data = $this->notFoundException($event);
        		break;	
        	case 500: 
        		// Dette er intern-feil, men kan også være egne kastede exceptions.
        	default:
        		$view_data['message'] = "Out of cheese error! Divide by cucumber, reinstall universe and try again";
        		break;
        }
        ####
        # Historical reference:
        # All kode jeg testa som til slutt viste seg å ikke være nødvendig (og som heller ikke funka)
        # Burde fjernes en gang
        ####
        //var_dump($this->container);
  		// $loader = new FilesystemLoader(__DIR__.'/../../UKMNorge/DeltaBundle/Resources/views/%name%');
  		// var_dump($loader);
		// $templating = new PhpEngine(new TemplateNameParser(), $loader);
		// Lag TwigEngine?
		// echo '<br>\r\nCreating TwigEngine';
		// $environment = new Twig_Environment();
		// //$parser = new TemplateNameParserInterface();
		// //$locator = new FileLocatorInterface();
		// //var_dump($environment);
		// $templating = new TwigEngine($environment, TemplateNameParserInterface, FileLocatorInterface);
		
		//$response->setContent($templating->render('Error/index.html.twig', array('message' => 'test')));

        // $message = 'Ooops, feil! Koden sier: ' . $exception->getMessage(); 
		// var_dump($this->templating);  
        // $response->setContent($message);

        // HttpExceptionInterface is a special type of exception that
        // holds status code and header details
        // if ($exception instanceof HttpExceptionInterface) {
        //     $response->setStatusCode($exception->getStatusCode());
        //     $response->headers->replace($exception->getHeaders());
        // } else {
        //     $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        // }
        
        // La Twig rendre i vei
        $response = $this->container->get('templating')->render('UKMDeltaBundle:Error:index.html.twig', $view_data);
        // Send data til nettleseren
        echo $response;

        // Her sendes data til nettleseren.
        //$event->setResponse($response);
    }

    public function deltaException(GetResponseForExceptionEvent $event) {
    	// This function is expected to return an 
    	// array with text for exceptions generated within UKMdelta
    	$view_data['message'] = "Damn it, Carl!";


    	return $view_data;
    }
}
?>