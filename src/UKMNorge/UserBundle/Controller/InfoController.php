<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use UKMNorge\Geografi\Fylker;
use Exception;
use stdClass;
use UKMCURL;

require_once('UKM/Autoloader.php');

class InfoController extends Controller {

	/**
	 * InformationQueueAction skal kjøres fra LoginSuccessHandler når noe informasjon påkrevd av Scopes mangler.
	 * Her håndterer vi all informasjonsinnsamling, rendring av skjema, lagring av svar osv.
	 * Når alle skjema er utfylt og lagret, sendes brukeren tilbake til RedirectHandler.
	 *
	 */
	public function informationQueueAction(Request $request) {
		$view_data = array();
		$session = $this->container->get('session');
		// Last inn kø-informasjon
		$completed = $session->get('completed');
		if( null == $completed ) {
			$completed = array();
		}

		// Hvis vi er i retur fra facebook: 
		if( $session->get('facebook_return') ) {
			// Kun hvis facebookConnect funker kan vi sende brukeren videre.
			if( $this->facebookConnect() ) {
				// Oppdater kø-informasjon
				$completed[] = 'facebook';
			}
		}

		// Hvis vi er i retur fra et skjema:
		if( $request->request->get('skjema') ) {
			// Håndter respons
			$this->handleResponse($request);
			$completed[] = $request->request->get('skjema');	
		}
		$session->set('completed', $completed);

		// Har vi fullført alle skjema?
		$resterende = array_diff($session->get('information_queue'), $completed );
		if( empty( $resterende ) ) {
			$redirecter = $this->get('ukm_user.redirect');
			return $redirecter->doRedirect();
		}

		switch( current($resterende) ) {
            case 'kommune': 
                return $this->kommuneSkjema($view_data);
            break;
            case 'alder':
               	return $this->alderSkjema($view_data);
            break;	
            case 'facebook':
            	return $this->facebookSkjema($view_data);
            break;
            default:
            	// TODO: Prettify denne?
            	throw new Exception("Mangler skjemaet du spør om!");
            break;
        }
	}

	/**
	 * handleResponse skal lagre svarene vi får fra skjemaene.
	 * 
	 * @param Request $request
	 * @return Ingenting.
	 */
	private function handleResponse( Request $request ) {
		switch( $request->request->get('skjema') ) {
			case 'kommune':
				$user_service = $this->container->get('ukm_user');
				$user = $user_service->getCurrentUser();
				$user->setKommuneId($request->request->get('kommune_id'));
				$this->container->get('logger')->debug('UKMUserBundle:InfoController:handleResponse: Oppdaterer kommune-id til: '.$request->request->get('kommune_id'));
				$user_manager = $this->container->get('fos_user.user_manager');
				$user_manager->updateUser($user);
			break;
			// Ikke implementert - kaster likegreit ukjent skjema.
			case 'alder':
			default:
				throw new Exception("Ukjent skjema!");
			break;
		}
	}

	/**
	 * Skal rendre kommuneskjemaet.
	 *
	 */
	private function kommuneSkjema($view_data) {
		$liste = array();

		$view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['fylker'] = Fylker::getAll();;

        return $this->render('UKMUserBundle:Info:kommune.html.twig', $view_data);
	}

	/**
	 * Skal rendre alderskjemaet, som kun er et test-skjema, siden alder er en del av opprinnelig registrering.
	 *
	 */
	private function alderSkjema($view_data) {
		return $this->render('UKMUserBundle:Info:alder.html.twig', $view_data);
	}

	/**
	 * Skal rendre facebook-skjemaet som forklarer hvorfor og viser knappen "Koble til med facebook".
	 *
	 */
	private function facebookSkjema($view_data) {
		$app_id = $this->getParameter('facebook_client_id');
		if( 'ukm.dev' ==  $this->getParameter('UKM_HOSTNAME') ) {
			$redirectURL = 'https://delta.ukm.dev/app_dev.php/info';
		} else {
			$redirectURL = 'https://delta.ukm.no/info/';
		}
        
		$view_data = array();
		$view_data['fbredirect'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
        
        // Forteller templaten hvilken forklaring som skal inn.
		if ( null != $this->get('session')->get('rdirurl') ) {
		    $view_data['system'] = $this->get('session')->get('rdirurl');
		}

        $this->get('session')->set('facebook_return', true);

		return $this->render('UKMUserBundle:Info:facebook.html.twig', $view_data);
	}

	/**
	 * Skal håndtere retur fra facebook for lagring av facebook-id etter autorisering.
	 *
	 * @param
	 * @return true ved suksess, false ved feil
	 */
	private function facebookConnect() {
		$this->get('session')->remove('facebook_return');

        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        if( 'ukm.dev' == $this->getParameter('UKM_HOSTNAME') ) {
			$redirectURL = 'https://delta.ukm.dev/app_dev.php/info';
		} else {
			$redirectURL = 'https://delta.ukm.no/info/';
		}
        
        if ($req->query->get('code')) {
            $code = $req->query->get('code');
            // This means we are coming from facebook
            // Bytt code for en access-token
            $curl = new UKMCurl();
            $url = 'https://graph.facebook.com/v2.3/oauth/access_token';
            $url .= '?client_id='.$this->getParameter('facebook_client_id');
            $url .= '&redirect_uri='.$redirectURL;
            $url .= '&client_secret='.$this->getParameter('facebook_client_secret');
            $url .= '&code='.$code;
            $curl->timeout(50);
            $result = $curl->process($url);

			if(isset($result->error)) {    
            	$this->container->get('logger')->error("UKMUserBundle:InfoController:facebookConnect: Klarte ikke å bytte kode for access-token. Resultat: ".var_export($result, true) );
                return false;
            }

            $token = $result->access_token;
            // Hent brukerdata
            $url = 'https://graph.facebook.com/me';
            $url .= '?access_token='.$token;
            $fbUser = $curl->process($url);

            if (isset($fbUser->error)) {
                $this->container->get('logger')->error("UKMUserBundle:InfoController:facebookConnect: Klarte ikke å hente brukerdata. Resultat: ".var_export($fbUser, true) );
                return false;
            }

            // Fyll inn fb-data i brukertabellen
            $user = $this->get('ukm_user')->getCurrentUser();
            $user->setFacebookId($fbUser->id);

            $userManager = $this->container->get('fos_user.user_manager');
            $userManager->updateUser($user);

            return true;
        }
        return false;
    }
}
