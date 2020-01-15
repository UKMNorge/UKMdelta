<?php

#########
# This is the custom ExceptionHandler written for UKMDelta
# By Asgeir Stavik Hustad
# asgeirsh@ukmmedia.no
# UKM Norge / UKM Media
#########

namespace AppBundle\EventListener;

use Exception;

use Symfony\Component\HttpFoundation\Request;
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
use Symfony\Component\HttpFoundation\RedirectResponse;

require_once("UKMconfig.inc.php");
require_once("UKM/mail.class.php");
use UKMmail;

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

        ## Ignore-liste - disse gjør vi ingenting med - UANSETT
        if ($exception->getMessage() == "Call to a member function getPameldUser() on string") {
            # Vi redirecter bare brukeren til innlogging og hjemmeside dersom dette skjer. Feil kommer av at brukeren er logget ut (av oss eller invalid session, f.eks).
            $route = $this->container->get('router')->getRouteCollection()->get('ukm_delta_ukmid_homepage');
            echo new RedirectResponse($route->getPath());
            // Gjør som under - setter response i $event til en tom response for ikke å outputte feilmeldingen.
            $event->setResponse(new Response());
            return $event;
        }
        
        // Sjekk hvilken exception det er her.
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
                $this->notifySupport($event->getException());
        		break;
        }
        
        ## Code 100 = Exception fra UserService - Brukeren ikke logget inn. Force logout på vanlig symfony-vis.
        if( $code == 100 ) {
            $this->container->get('logger')->notice("ExceptionListener: Logger ut brukeren via symfony-logout.");
            $route = $this->container->get('router')->getRouteCollection()->get('fos_user_security_logout');
            $response = new RedirectResponse(
                $route->getPath()
            );
            $event->setResponse( $response );
        }
        # Dersom vi ikke har noe brukerobjekt (usertoken), sett en anon-token og logg ut brukeren. Ikke logg ut vanlige brukere som bare har hatt en feil som ikke er håndtert.
        if (NULL == $this->container->get('security.context')->getToken()) {
            $this->container->get('logger')->error("ExceptionListener: Mangler bruker-objekt! Logger ut brukeren via anon-token.");
            $view_data['code'] = $code;
            $usertoken = new UsernamePasswordToken("anon", "anon", "ukm_delta_wall", array("ROLE_USER"));
            $this->container->get('security.token_storage')->setToken($usertoken);
            // La Twig rendre i vei
            $response = $this->container->get('templating')->render('UKMDeltaBundle:Error:index.html.twig', $view_data);
            // Send data til nettleseren
            echo $response;
            // Setter denne til en tom response for å stoppe original varsling i tillegg til vår egen.
            $event->setResponse(new Response());
            return new Response();
        }
    }

    /** 
     * This function is expected to return an array with text for exceptions generated within UKMdelta
     * May also return any of the keywords used in DeltaBundle:Error:index.html.twig
     */
    public function deltaException(GetResponseForExceptionEvent $event) {
    	
        $message = $event->getException()->getMessage();

        if ($message == 'Du har ikke tilgang til dette innslaget!') {
            $this->container->get('logger')->error("ExceptionListener: Ikke tilgang til innslag. File: ".$_SERVER['REQUEST_URI']);

            ## Tekst
            $key = 'feil.ingentilgang.';
            
            $view_data['overskrift'] = $key.'topptekst';
            $view_data['ledetekst'] = $key.'ledetekst';
            $view_data['tekst'] = $key.'tekst';

        }
        elseif ($message == 'Feil kategori for innslaget! Vi videresender deg nå.') {
            $this->container->get('logger')->error("ExceptionListener: Feil kategori. File: ".$_SERVER['REQUEST_URI']);

            // Her kommer det en redirect, 
            $key = 'feil.kategori.';
            $view_data['overskrift'] = $key.'topptekst';
            $view_data['ledetekst'] = $key.'ledetekst';
        }
        elseif ($message == 'Påmeldingsfristen er ute!') {
            $this->container->get('logger')->notice("ExceptionListener: Påmeldingsfrist ute-exception!");

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
            $this->container->get('logger')->error("ExceptionListener: Totalt ukjent. File: ".$_SERVER['REQUEST_URI']);

            $this->notifySupport($event->getException());
            
            $key = 'feil.ukjentfeil.';
            $view_data['ledetekst'] = $key.'topptekst';
        
            $teknisk = array();
            $teknisk['message'] = $message;
            $teknisk['file'] = $event->getException()->getFile();
            $teknisk['line'] = $event->getException()->getLine();
            $teknisk['trace'] = $event->getException()->getTraceAsString();
        
            $this->container->get('logger')->error("Trace data: ".var_export($teknisk, true));

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
        
        $this->container->get('logger')->error("ExceptionListener: Ukjent. File: ".$_SERVER['REQUEST_URI']);

        $teknisk = array();
        $teknisk['message'] = $event->getException()->getMessage();
        $teknisk['file'] = $event->getException()->getFile();
        $teknisk['line'] = $event->getException()->getLine();
        $teknisk['trace'] = $event->getException()->getTraceAsString();
        //$teknisk['exception'] = $event->getException();
        $view_data['teknisk'] = $teknisk;

        $this->container->get('logger')->error("Trace data: ".var_export($teknisk, true));
        return $view_data;
    }
    public function notFoundException(GetResponseForExceptionEvent $event) {
        // This function is expected to return an 
        // array with text for exceptions created when no file was found.
        // The text returned should be a key in translations/base.
        // May also return any of the keywords used in DeltaBundle:Error:index.html.twig
        
        $this->container->get('logger')->error("ExceptionListener: 404. File: ".$_SERVER['REQUEST_URI']);

        $key = 'feil.ikkefunnet.';

        $view_data['overskrift'] = $key.'topptekst';
        $view_data['ledetekst'] = $key.'ledetekst';
        $view_data['tekst'] = $key.'tekst';

        // Humornøkkel
        $view_data['sadface'] = false;

        return $view_data;
    }

    public function notifySupport(Exception $e, $header = null) {
        $request = Request::createFromGlobals();
        $user = $this->container->get('security.context')->getToken()->getUser();

        $message = "En ukjent feil har oppstått i Delta ".date("Y-m-d H:i:s").".\n\nDebug-informasjon:\n";
        if($header != null) {
            $message .= "Feilmelding: ".$header."\n";
        }

        $message .= "\n<b>Exception:</b> ".$e->getCode().", ".$e->getMessage();
        $message .= "\n<b>Route:</b> ".$request->server->get('REQUEST_URI');
        if(is_object($user) ) {
            $message .= "\n<b>User:</b> ".$user->getName(). ', <b>ID:</b> '.$user->getId();    
        } else {
            $message .= "\n<b>User:</b> ".$user." - ikke et bruker-objekt! Er brukeren blitt logget ut underveis?";
        }
        
        $message .= "\n<b>Debug backtrace:</b> \n".$e->getTraceAsString();

        if ( $this->container->getParameter("kernel.environment") == 'dev' || (defined('UKM_HOSTNAME') && UKM_HOSTNAME == 'ukm.dev')) {
            echo 'Utviklingsmodus, sender ikke e-post til support.<br>';
            echo nl2br($message);
        } 
        else {
            $this->container->get('logger')->info("UKMdelta: Notifying support of the issue");
            $mail = new UKMmail();
            $ok = $mail->to('support@ukm.no')
                ->setFrom('delta@ukm.no', 'UKMdelta')
                ->setReplyTo('delta@ukm.no', 'UKMdelta')
                ->subject('Feil oppstått i Delta '.date("Y-m-d H:i:s"))
                ->message($message)
                ->ok();
            if(!$ok) {
                $this->container->get('logger')->error("UKMdelta: Fikk ikke til å sende info til support om Exception!");
                return false;
            }
            return true;
        }
    }
}
?>