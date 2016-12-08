<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Exception;
use stdClass;
use UKMCURL;

class InfoController extends Controller {

	/**
	 * InformationQueueAction skal kjøres fra LoginSuccessHandler når noe informasjon påkrevd av Scopes mangler.
	 * Her håndterer vi all informasjonsinnsamling, rendring av skjema, lagring av svar osv.
	 * Når alle skjema er utfylt og lagret, sendes brukeren tilbake til RedirectHandler (TODO).
	 *
	 */
	public function informationQueueAction(Request $request) {
		$view_data = array();
		$session = $this->container->get('session');

		// Hvis vi er i retur fra et skjema:
		if( $request->request->get('infoQueue') ) {
			// Håndter respons
			$this->handleResponse($request);

			// Last inn kø-informasjon
			$infoQueue = json_decode( $request->request->get('infoQueue') );
			$infoQueue[] = $request->request->get('skjema');
			$view_data['infoQueue'] = json_encode($infoQueue);

			// Har vi fullført alle skjema?
			$resterende = array_diff($session->get('information_queue'), $infoQueue );
			if( empty( $resterende ) ) {
				$redirecter = $this->get('ukm_user.redirect');
				return $redirecter->doRedirect();
				throw new Exception("All done! Nå må vi bare redirecte til en LoginSuccessHandler eller tilsvarende.");
			}
		} 
		else {
			$view_data['infoQueue'] = json_encode(array());
			$resterende = $session->get('information_queue');
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
				// TODO: SAVE DATA
			break;
			case 'alder':
				// TODO: SAVE DATA (OR REMOVE!)
			break;
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

		$fylke = new stdClass();
		$fylke->navn = "Fylke";
		$fylke->id = 1;
		$fylke->kommuner = array();

		$kommune = new stdClass();
		$kommune->id = 1;
		$kommune->navn = "Kommunenavn";
			
		$fylke->kommuner[] = $kommune;
		$liste[] = $fylke;

		$view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['fylker'] = $liste;

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
		if( $this->getParameter('UKM_HOSTNAME') ) {
			$redirectURL = 'http://delta.ukm.dev/web/app_dev.php/info';
		} else {
			$redirectURL = 'http://delta.ukm.no/info/';
		}
        
        $view_data = array();
        $view_data['fbredirect'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
        
        $rdirurl = $this->get('session')->get('rdirurl');
        if ($rdirurl == 'ambassador') {
            $view_data['system'] = 'ambassador';
        }

        $this->get('session')->set('facebook_return', true);

		return $this->render('UKMUserBundle:Info:facebook.html.twig', $view_data);
	}

	/**
	 * Skal håndtere retur fra facebook for lagring av facebook-id etter autorisering.
	 *
	 * @param
	 * @return 
	 */
	private function facebookConnect() {
		$this->get('session')->remove('facebook_return');

        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        if( $this->getParameter('UKM_HOSTNAME') ) {
			$redirectURL = 'http://delta.ukm.dev/web/app_dev.php/info';
		} else {
			$redirectURL = 'http://delta.ukm.no/info/';
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
                var_dump($result);
                die();
            }

            $token = $result->access_token;
            // Hent brukerdata
            $url = 'https://graph.facebook.com/me';
            $url .= '?access_token='.$token;
            $fbUser = $curl->process($url);

            if (isset($fbUser->error)) {
                var_dump($fbUser);
                die();
            }

            // Fyll inn fb-data i brukertabellen
            $user = $this->get('ukm_user')->getCurrentUser();
            $user->setFacebookId($fbUser->id);

            $userManager = $this->container->get('fos_user.user_manager');
            $userManager->updateUser($user);
        }
    }
}