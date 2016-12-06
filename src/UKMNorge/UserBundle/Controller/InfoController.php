<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Exception;
use stdClass;

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
            default:
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
}