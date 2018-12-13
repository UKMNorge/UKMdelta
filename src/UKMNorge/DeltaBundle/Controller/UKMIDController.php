<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use person;
use UKMCurl;

class UKMIDController extends Controller
{
    public function indexAction()
    {
        require_once("UKM/innslag.class.php");
	    $view_data = array();
        $view_data['translationDomain'] = 'ukmid';
	    $user = $this->get('ukm_user')->getCurrentUser();
	    $view_data['user'] = $user;
        $innslagService = $this->get('ukm_api.innslag');
        $season = $this->get('ukm_delta.season')->getActive();

        $innslagsliste = array();

        // List opp påmeldte og ikke fullførte innslag denne brukeren er kontaktperson for
        $contact_id = $user->getPameldUser();
        $innslagsliste = $innslagService->hentInnslagFraKontaktperson($contact_id, $user->getId());
        
		// Sjekk opp frist for alle innslagene
		foreach( $innslagsliste as $gruppe => $alle_innslag ) {
			foreach( $alle_innslag as $innslag ) {
		        $innslag->monstring = $innslag->innslag->min_lokalmonstring( $season );
		        $innslag->tittellos = $innslag->innslag->tittellos();
		        $innslag->pamelding_apen = $innslag->monstring->subscribable( 'pl_deadline'. ($innslag->tittellos ? '2':'') );
	        }
        }
        $view_data['dinside'] = true;
        $view_data['alle_innslag'] = $innslagsliste;
        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }


    public function personvernAction()
    {
		$view_data = [
			'translationDomain' => 'ukmid',
		];

		return $this->render('UKMDeltaBundle:UKMID:personvern.html.twig', $view_data );
	}
	
    /**
     * checkPersonvern
     * Kontrollerer brukerens svar på samtykke-spørsmålet
     */
    public function checkPersonvernAction( Request $request )
    {
        $userManager = $this->container->get('fos_user.user_manager');
        $user = $this->get('ukm_user')->getCurrentUser();

        // Lagre svaret
        $user->setSamtykke( $request->request->get('personvern') == 'ja' );
        $userManager->updateUser($user);
        
        return $this->redirectToRoute('ukm_delta_ukmid_checkinfo');
	}
	
    public function requestAgeAction() {
        $view_data = [
            'translationDomain' => 'ukmid'
        ];
        
        $userManager = $this->container->get('fos_user.user_manager');
        $user = $this->get('ukm_user')->getCurrentUser();

        // Beregn alder fra fødselsår
        if( $user->getBirthdate() !== null ) {
            $now = new DateTime('now');
            $age = $user->getBirthdate()->diff($now)->y;
            $view_data['age'] = $age;
        }

        return $this->render('UKMDeltaBundle:UKMID:alder.html.twig', $view_data );
    }

    public function saveAgeAction() {
        $dato = new DateTime('now');
        $userManager = $this->container->get('fos_user.user_manager');
        $user = $this->get('ukm_user')->getCurrentUser();

        // Ta imot post-variabler
        $request = Request::createFromGlobals();
        $age = $request->request->get('age');

        // Beregn birthdate basert på alder
        if ($age == 0) {
            // Tilsvarer UNIX Timestamp = 0. Kunne også lagra som en int.
            $dato->setTimestamp(0);
        } else {
            $birthYear = (int)date('Y') - $age;
            $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
            $dato->setTimestamp($birthdate);
        }
        
        // Legg til verdier i user-bundle
        $user->setBirthdate($dato);

        if( $age > 0 && $age < 15 ) {
            $navn = $request->request->get('foresatt_navn');
            $mobil = $request->request->get('foresatt_mobil');
            $user->setForesattNavn( $navn );
            $user->setForesattMobil( $mobil );
        }

        $userManager->updateUser($user);
        
        // Alt lagret ok, gå tilbake og sjekk at vi har alt.
        return $this->redirectToRoute('ukm_delta_ukmid_checkinfo');
    }

    public function checkInfoAction()
    {
        $user = $this->get('ukm_user')->getCurrentUser();
        
        if( $user->getBirthdate() == null ) {
            // Gå til spørsmål om alder
            return $this->redirectToRoute('ukm_delta_ukmid_alder');
        } else {
            // Deltakere under 15, som tidligere har oppgitt alder,
            // men ikke oppgitt foresatte må innom alder og foresatt-siden på nytt
            $now = new DateTime('now');
            $age = $user->getBirthdate()->diff($now)->y;
            
            if( $age < 15 && $user->getForesattMobil() == null ) {
                return $this->redirectToRoute('ukm_delta_ukmid_alder');
            }
        }

        if( $user->getSamtykke() === null ) {
            return $this->redirectToRoute('ukm_delta_ukmid_personvern');
        }
        
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding');
    }

    public function editContactAction() {
        $view_data = array();
        require_once('UKM/person.class.php');
        $personService = $this->get('ukm_api.person');
        $user = $this->get('ukm_user')->getCurrentUser();

        if ($user->getPameldUser() == null) {
           
            // Hent alder fra UserBundle
            $view_data['epost'] = $user->getEmail();
            //$view_data['age'] = null;
        }
        else {
            $person = $personService->hent($user->getPameldUser());
            $view_data['person'] = $person; 
            $view_data['age'] = $personService->alder($person);
            if ($view_data['age'] == '25+') {
                $view_data['age'] = 0;
            }
            $view_data['epost'] = $person->get('p_email');
        }
        
       
        $view_data['translationDomain'] = 'ukmid';
        $view_data['user'] = $user;        
        // $person = new person($user->getPameldUser());
        return $this->render('UKMDeltaBundle:UKMID:contact.html.twig', $view_data);
    }

    public function saveContactAction() {
        require_once('UKM/person.class.php');
        $user = $this->get('ukm_user')->getCurrentUser();
        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->container->get('ukm_api.innslag');
        
        // Ta i mot post-variabler
        $request = Request::createFromGlobals();

        // Dette vet vi alltid
        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $mobil = $request->request->get('mobil');
        $epost = $request->request->get('epost');

        // Dette vet vi kun om personen har meldt på et innslag!
        if ($user->getPameldUser() != null) {
            $person = new person($user->getPameldUser());
            // Alder
            $alder = $request->request->get('age');
            // Beregn birthdate basert på age?
            if ($alder != 0) {
                $birthYear = (int)date('Y') - $alder;
            }
            else {
                $birthYear = 1970;
            }
            $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
            $dato = new DateTime('now');
            $dato->setTimestamp($birthdate);
            $user->setBirthdate($dato);
            $person->set('p_dob', $dato->getTimestamp());
            
            // Adresse
            $adresse = $request->request->get('adresse');
            $user->setAddress($adresse);
            $person->set('p_adress', $adresse);

            $postnummer = $request->request->get('postnummer');
            $user->setPostNumber($postnummer); 
            $person->set('p_postnumber', $postnummer);
            
            // Poststed
            $poststed = $request->request->get('poststed'); 
            $user->setPostPlace($poststed);
            $person->set('p_postplace', $poststed);

            // Lagre til databasen
            $person->set('p_firstname', $fornavn);
            $person->set('p_lastname', $etternavn);
            $person->set('p_email', $epost);
            $person->set('p_phone', $mobil);
            
            $person->lagre('delta', $user->getPameldUser());

            // Har personen et eller flere tittelløse innslag?
            // I så fall, oppdater navn på disse
            $innslagsliste = $innslagService->hentInnslagFraKontaktperson($user->getPameldUser(), $user->getId());
            //var_dump($innslagsliste);
            foreach ($innslagsliste['fullstendig'] as $innslag) {
                if ($innslag->innslag->tittellos()) {
                    // Innslaget er et tittelløst innslag
                    $innslagService->lagreArtistnavn($innslag->innslag->get('b_id'), $fornavn . ' '. $etternavn);
                }
            }
            foreach ($innslagsliste['ufullstendig'] as $innslag) {
                if ($innslag->innslag->tittellos()) {
                    // Innslaget er et tittelløst innslag
                    $innslagService->lagreArtistnavn($innslag->innslag->get('b_id'), $fornavn . ' '. $etternavn);
                }
            }
        }
        

        
        // Lagre til UserBundle
        $user->setFirstName($fornavn);
        $user->setLastName($etternavn);
        $user->setPhone($mobil);
        $user->setEmail($epost);
    
        // Lagre user
        $userManager->updateUser($user);

        // Legg til info om det gikk bra
        $this->addFlash('success', 'Endringene ble lagret!');
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }

    public function supportAction() {
        $view_data['translationDomain'] = 'ukmid';
        return $this->render('UKMDeltaBundle:UKMID:support.html.twig', $view_data);
    }

    public function fbconnectAction() {
        // Sjekk om brukeren er koblet til med facebook allerede, i så fall redirect til ukmid
        $user = $this->get('ukm_user')->getCurrentUser();
        if ($user->getFacebookId()) {
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
        
        // Hvis ikke, kjør facebook-tilkoblings-kode:
        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        $redirectURL = 'https://delta.'.($this->getParameter('UKM_HOSTNAME') == 'ukm.dev' ? 'ukm.dev'.'/app_dev.php' : $this->getParameter('UKM_HOSTNAME')) . '/ukmid/fbconnect';

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
            // var_dump($url);
            // var_dump($curl);
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
            $user->setFacebookId($fbUser->id);

            $userManager = $this->container->get('fos_user.user_manager');
            $userManager->updateUser($user);

            // Gjennomfør loginsuccess, så man blir redirectet om man skal bli redirectet?
            $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
            $usertoken = $this->get('security.token_storage')->getToken(); 
            $response = $handler->onAuthenticationSuccess($req, $usertoken);
            return $response;
            // Success!
            // return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        $app_id = $this->getParameter('facebook_client_id');
        $view_data = array();
        $view_data['fbredirect'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
        
        $rdirurl = $this->get('session')->get('rdirurl');
        if ($rdirurl == 'ambassador') {
            $view_data['system'] = 'ambassador';
        }
        

        return $this->render('UKMDeltaBundle:UKMID:fbconnect.html.twig', $view_data);
    }

}
