<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use UKMCurl;
use Exception;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Innslag\Personer\Write as WritePerson;
use UKMNorge\Log\Logger;

require_once("UKM/Autoloader.php");


class UKMIDController extends Controller
{
    /**
     * Viser alle innslag kontaktpersonen har
     * _@route: </ukmid/>
     * 
     */
    public function indexAction()
    {
        try {
            $user = $this->get('ukm_user')->getCurrentUserAsObject();
        } catch( Exception $e ) {
            return $this->redirectToRoute('fos_user_security_logout');
        }
        
        $view_data = [
            'translationDomain' => 'ukmid',
            'user' => $user,
            'dinside' => true,
            'alle_innslag' => $this->get('ukm_api.innslag')->hentInnslagFraKontaktperson()
        ];

        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }

    /**
     * Lar brukeren ta stilling til personvern
     * _@route: </ukmid/personvern>
     *
     */
    public function personvernAction()
    {
		$view_data = [
			'translationDomain' => 'ukmid',
		];

		return $this->render('UKMDeltaBundle:UKMID:personvern.html.twig', $view_data );
	}
	
    /**
     * Kontrollerer brukerens svar på samtykke-spørsmålet
     * _@route: POST </ukmid/personvern>
     * 
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
    
    /**
     * Hvis brukeren ikke har angitt alder, spør nå
     * _@route: </ukmid/....>
     *
     */
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

    /**
     * Lagre brukerens alder
     * _@route: </ukmid/....>
     */
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

    /**
     * Sjekk hvilken info vi har om brukeren, og send til riktig
     * side for å innhente det vi mangler
     *
     * @return void
     */
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

    /**
     * Rediger kontaktpersonen
     *
     * @return void
     */
    public function editContactAction() {
        $personService = $this->get('ukm_api.person');
        $user = $this->get('ukm_user')->getCurrentUser();

        $view_data = [
            'translationDomain' => 'ukmid',
            'user' => $user
        ];

        // Brukeren har ikke et assosiert Person-objekt (deltaker)
        if ($user->getPameldUser() == null) {
            $view_data['epost'] = $user->getEmail();
        }
        // Brukeren har et Person-objekt tilkoblet sin konto
        else {
            $person = $personService->hent($user->getPameldUser());
            $view_data['person'] = $person;
            $view_data['epost'] = $person->getEpost();
        }
        return $this->render('UKMDeltaBundle:UKMID:contact.html.twig', $view_data);
    }

    /**
     * Lagre endringer i kontaktperson
     *
     * @return void
     */
    public function saveContactAction() {
        // Ta i mot post-variabler
        $request = Request::createFromGlobals();

        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->container->get('ukm_api.innslag');

        $user = $this->get('ukm_user')->getCurrentUser();
        
        // POST-verdier
        // Disse vet vi alltid
        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $mobil = $request->request->get('mobil');
        $epost = $request->request->get('epost');

        // Lagre til UserBundle
        $user->setFirstName($fornavn);
        $user->setLastName($etternavn);
        $user->setPhone($mobil);
        $user->setEmail($epost);

        // Dette vet vi kun om personen har meldt på et innslag!
        if ($user->getPameldUser() != null) {
            // POST-verdier
            $adresse = $request->request->get('adresse');
            $postnummer = $request->request->get('postnummer');
            $poststed = $request->request->get('poststed'); 
            $fodselsdato = WritePerson::fodselsdatoFraAlder($request->request->get('age')); // Alder
            
            // Oppdater bruker
            if( $fodselsdato != 0 ) {
                $user->setBirthdate($fodselsdato);
            }
            $user->setPostNumber($postnummer); 
            $user->setAddress($adresse);
            $user->setPostPlace($poststed);
            
            // Oppdater personobjekt
            $person = new Person($user->getPameldUser());
            $person->setAdresse($adresse);
            $person->setPostnummer($postnummer);
            $person->setPoststed($poststed);
            $person->setFodselsdato($fodselsdato);
            $person->setFornavn($fornavn);
            $person->setEtternavn($etternavn);
            $person->setMobil($mobil);
            $person->setEpost($epost);
            
            // Bruker ikke personService, da vi mangler mønstringinfo
            Logger::setID('delta_Dinside', $user->getId(), 1);
            WritePerson::save( $person );

            // Har personen et eller flere tittelløse innslag?
            // I så fall, oppdater navn på disse
            $alle_innslag = $innslagService->hentInnslagFraKontaktperson($user->getPameldUser(), $user->getId());
            foreach( $alle_innslag->getAll() as $innslag ) {
                if( $innslag->getType()->erJobbeMed() ) {
                    $innslag->setNavn( $person->getNavn() );
                    $innslagService->lagre( $innslag );
                }
            }
        }
        
        // Lagre user
        $userManager->updateUser($user);

        // Legg til info om det gikk bra
        $this->addFlash('success', 'Endringene ble lagret!');
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }

    /**
     * Vis support-siden
     *
     * @return void
     */
    public function supportAction() {
        return $this->render(
            'UKMDeltaBundle:UKMID:support.html.twig',
            [
                'translationDomain' =>'ukmid'
            ]
        );
    }







    public function fbconnectAction() {
        throw new Exception('TODO: Funksjonen er ikke implementert');

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
