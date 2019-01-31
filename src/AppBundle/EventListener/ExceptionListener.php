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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ExceptionListener {
	
	// protected $templating;

	public function __construct($container) {
		$this->container = $container;
	}

	 /**
     * Handles a kernel exception and returns a relevant response.
     *
     * Aims to deliver content to the user that explains the exception, rather than falling
     * back on symfony's exception handler which displays a less verbose error message and nothing in production.
     *
     * @param GetResponseForExceptionEvent $event The exception event
     */
    public function onKernelException(GetResponseForExceptionEvent $event) 
    {
        $exception = $event->getException();
        $this->container->get('logger')->error("ExceptionListener: ERROR: Unhandled Exception occurred. Event-data: ", array("event" => $event));
        
        $code = -1;
        $view_data = array();
        
        // TODO: Bytt ut denne med != klasser som implementerer HttpExceptionInterface og inverter
        if (in_array('HttpExceptionInterface', class_implements($exception))) {
            $code = $exception->getStatusCode();
        } 
        elseif (get_class($exception) == "Symfony\Component\HttpKernel\Exception\NotFoundHttpException") {
            $code = 404;
        }
        else {
            $code = $exception->getCode();
        }

        $response = new Response();
        
        // Sjekk hvilken exception det er her
        // TODO: Logg stuff her
        $view_data = array();
        switch ($code) {
        	case 0:
        		// Egne exceptions uten statuskode dukker opp her!
        		$view_data = $this->deltaException($event);
        		break;
        	case 404:
        		// Not found Exception
        		$view_data = $this->notFoundException($event);
        		break;	
        	case 500: 
        		// Dette er intern server-feil, men kan også være egne kastede exceptions.
            default:
                $view_data = $this->unknownErrorException($event);
        		break;
        }
        
        $view_data['code'] = $code;
        $usertoken = new UsernamePasswordToken("anon", "anon", "ukm_delta_wall", array("ROLE_USER"));
        $this->container->get('security.token_storage')->setToken($usertoken);
        // La Twig rendre i vei
        $response = $this->container->get('templating')->render('UKMDeltaBundle:Error:index.html.twig', $view_data);
        // Send data til nettleseren
        echo $response;

        // Setter denne til en tom response for å stoppe original varsling i tillegg til vår egen.
        $event->setResponse(new Response());
    }

    /** 
     * This function is expected to return an array with text for exceptions generated within UKMdelta
     * May also return any of the keywords used in DeltaBundle:Error:index.html.twig
     */
    public function deltaException(GetResponseForExceptionEvent $event) {
    	
        $message = $event->getException()->getMessage();

        if ($message == 'Du har ikke tilgang til dette innslaget!') {
            ## Tekst
            $key = 'feil.ingentilgang.';
            
            $view_data['overskrift'] = $key.'topptekst';
            $view_data['ledetekst'] = $key.'ledetekst';
            $view_data['tekst'] = $key.'tekst';

        }
        elseif ($message == 'Feil kategori for innslaget! Vi videresender deg nå.') {
            // Her kommer det en redirect, 
            $key = 'feil.kategori.';
            $view_data['overskrift'] = $key.'topptekst';
            $view_data['ledetekst'] = $key.'ledetekst';
        }
        elseif ($message == 'Påmeldingsfristen er ute!') {
            $key = 'frist.';
            $view_data['frist'] = true;
            $view_data['overskrift'] = $key.'overskrift';
            if ($this->container->get('request')->get('b_id')) {
                $view_data['ledetekst'] = $key.'ledetekst.tidligere';
            }
            else {
                $view_data['ledetekst'] = $key.'ledetekst.nytt';    
            }
            $view_data['pl_id'] = $this->container->get('request')->get('pl_id');
        }
        else {
            $key = 'feil.ukjentfeil.';

            $view_data['ledetekst'] = $key.'topptekst';
            // $view_data['tekst'] = $key.'tekst';
            $teknisk = array();
            $teknisk['message'] = $message;
            $teknisk['file'] = $event->getException()->getFile();
            $teknisk['line'] = $event->getException()->getLine();
            $teknisk['trace'] = $event->getException()->getTraceAsString();
            //$teknisk['exception'] = $event->getException();
            $view_data['teknisk'] = $teknisk;

        }

    	return $view_data;
    }
    public function unknownErrorException(GetResponseForExceptionEvent $event) {
        // This function is expected to return an 
        // array with text for any unknwon exceptions that occur.
        // The text returned should be a key in translations/base.
        // May also return any of the keywords used in DeltaBundle:Error:index.html.twig
        $key = 'feil.ukjentfeil.';

        $view_data['overskrift'] = 'feil.overskrift';
        $view_data['ledetekst'] = $key.'topptekst';
        // $view_data['ledetekst'] = $key.'ledetekst';
        // $view_data['tekst'] = $key.'tekst';


        $teknisk = array();
        $teknisk['message'] = $event->getException()->getMessage();
        $teknisk['file'] = $event->getException()->getFile();
        $teknisk['line'] = $event->getException()->getLine();
        $teknisk['trace'] = $event->getException()->getTraceAsString();
        //$teknisk['exception'] = $event->getException();
        $view_data['teknisk'] = $teknisk;

        return $view_data;
    }
    public function notFoundException(GetResponseForExceptionEvent $event) {
        // This function is expected to return an 
        // array with text for exceptions created when no file was found.
        // The text returned should be a key in translations/base.
        // May also return any of the keywords used in DeltaBundle:Error:index.html.twig
        
        $key = 'feil.ikkefunnet.';

        $view_data['overskrift'] = $key.'topptekst';
        $view_data['ledetekst'] = $key.'ledetekst';
        $view_data['tekst'] = $key.'tekst';

        // Humornøkkel
        $view_data['sadface'] = true;

        return $view_data;
    }
}
?>