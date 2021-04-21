<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use DateTime;
use UKMCurl;
use Exception;
use UKMNorge\APIBundle\Services\PersonService;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Innslag\Personer\Write as WritePerson;
use UKMNorge\Log\Logger;
use UKMNorge\Innslag\Personer\Kontaktperson;
use UKMNorge\Samtykke\Person as PersonSamtykke;


require_once("UKM/Autoloader.php");


class UKMIDController extends Controller
{
    /**
     * Viser alle innslag kontaktpersonen har.
     * Håndterer også eventuelle feilmeldinger som en bruker har blitt sendt tilbake til UKMID med.
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

        // Ved retur fra WP-innlogging kan du ha fått med deg en feilmelding. Vi printer den her:
        if( $this->get('request')->query->get('feilkode') == 1)  {
            # Vi sier ikke automatisk fra til support, velger i stedet å håndtere feil med å vise den til brukeren.
            $this->container->get("logger")->error("UKMIDController:index - Innlogging feilet i Wordpress. Det kan være at brukeren ikke har fått lov til å logge inn, eller at noe er feil i Wordpress-oppsettet.");
            $this->addFlash('danger', "Innlogging feilet i arrangørsystemet. Har du fått lov til å logge inn av arrangøren?");
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

        if( $this->get('session')->has('checkInfoRedirect') ) {
            $view_data['k_id'] = $this->get('session')->get('checkInfo_kid');
            $view_data['pl_id'] = $this->get('session')->get('checkInfo_plid');
            return $this->redirectToRoute($this->get('session')->get('checkInfoRedirect'), $view_data);
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
     * Viser skjema for å endre valget om fotoreservasjon.
     *
     * @return void
     */
    public function endrefotoreservasjonAction() {      
        $view_data = [
			'translationDomain' => 'ukmid',
		];

		return $this->render('UKMDeltaBundle:UKMID:fotoreservasjon.html.twig', $view_data );
    }
    

     /**
     * Lagre endringer i fororeservasjon
     *
     * @return void
     */
    public function saveEndrefotoreservasjonAction() {
        $request = Request::createFromGlobals();
        $samtykkeFraBruker = $request->request->get('personvern');
        $samtykkeFraBruker = $samtykkeFraBruker === 'ja' ? true : false;
        
        $personService = $this->get('ukm_api.person');

        $user = $this->get('ukm_user')->getCurrentUser();
        $pameld_user = $user->getPameldUser();

        if( null != $pameld_user ) {
            $person = new Kontaktperson( $pameld_user );
            foreach( $person->getInnslag()->getAll() as $innslag ) {
                try{
                    // Legger til samtykke til user objektet
                    $user->setSamtykke($samtykkeFraBruker);
                    // Opdaterer personvern for bruker med innslag
                    $personService->oppdaterPersonvern($innslag);
                } catch(Exception $e) {
                    $this->addFlash('danger', 'Noe gikk galt med lagring!');
                    return $this->redirectToRoute('ukm_delta_ukmid_homepage');
                }
            }
        }

        $this->addFlash('success', 'Endringene ble lagret!');
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
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
            /*
            $adresse = $request->request->get('adresse');
            $postnummer = $request->request->get('postnummer');
            $poststed = $request->request->get('poststed'); 
            */
            $fodselsdato = WritePerson::fodselsdatoFraAlder($request->request->get('alder')); // Alder
            
            // Oppdater bruker
            if( $fodselsdato != 0 ) {
                $dateTimeDato = new DateTime('now');
                $dateTimeDato->setTimestamp($fodselsdato);
                $user->setBirthdate($dateTimeDato);
            }
            /*
            $user->setPostNumber($postnummer); 
            $user->setAddress($adresse);
            $user->setPostPlace($poststed);
            */
            // Oppdater personobjekt
            $person = new Person($user->getPameldUser());
            /*
            $person->setAdresse($adresse);
            $person->setPostnummer($postnummer);
            $person->setPoststed($poststed);
            */
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
        $log = $this->get('logger');
        $log->info("fbconnectAction");
        // Sjekk om brukeren er koblet til med facebook allerede, i så fall redirect til ukmid
        $user = $this->get('ukm_user')->getCurrentUser();
        if ($user->getFacebookId()) {
            $log->info("User already connected - setting flashbag and skipping to homepage.");
            $this->addFlash("success", "Koblet til Facebook - neste gang kan du logge inn ved å trykke på Logg inn med Facebook-knappen!");
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        // Hvis ikke, kjør facebook-tilkoblings-kode:
        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        $redirectURL = 'https://delta.'.($this->getParameter('UKM_HOSTNAME') == 'ukm.dev' ? 'ukm.dev'.'/app_dev.php' : $this->getParameter('UKM_HOSTNAME')) . '/ukmid/fbconnect';

        if ($req->query->get('code')) {
            $log->info("Found code from facebook - fetching token.");
            $code = $req->query->get('code');
            // This means we are coming from facebook
            // Bytt code for en access-token
            $curl = new UKMCurl();
            $url = 'https://graph.facebook.com/v5.0/oauth/access_token';
            $url .= '?client_id='.$this->getParameter('facebook_client_id');
            $url .= '&redirect_uri='.$redirectURL;
            $url .= '&client_secret='.$this->getParameter('facebook_client_secret');
            $url .= '&code='.$code;
            $curl->timeout(50);
            
            $result = $curl->process($url);
            if(isset($result->error)) {
                $log->error("Failed to fetch token from facebook.", ["result" => $result]);
                $this->addFlash("danger", "Klarte ikke å koble til Facebook. Ga du oss tillatelse?");
                return $this->redirectToRoute('ukm_delta_ukmid_homepage');
            }

            $token = $result->access_token;
            $log->info("Got token, swapping it for user data");
            // Hent brukerdata
            $url = 'https://graph.facebook.com/me';
            $url .= '?access_token='.$token;
            $fbUser = $curl->process($url);

            if (isset($fbUser->error)) {
                $log->error("Failed to fetch user data from facebook.", ["fbUser" => $fbUser]);
                $this->addFlash("danger", "Klarte ikke å hente data fra Facebook. Har du gitt oss tillatelse?");
                return $this->redirectToRoute('ukm_delta_ukmid_homepage');
            }

            // Fyll inn fb-data i brukertabellen
            $user->setFacebookId($fbUser->id);
            $userManager = $this->container->get('fos_user.user_manager');
            $userManager->updateUser($user);

            // Gjennomfør loginsuccess, så man blir redirectet til andre sider enn Delta om man skal bli det.
            $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
            $usertoken = $this->get('security.token_storage')->getToken(); 
            $response = $handler->onAuthenticationSuccess($req, $usertoken);
            return $response;
        } elseif ( $req->query->get('error') ) {
            $log->error("Something failed when connecting to facebook. User was redirected back with error. Maybe user clicked cancel?", ["request params:" => $req->query->all()]);
            $this->addFlash("danger", "Klarte ikke å hente data fra Facebook. Har du gitt oss tillatelse?");
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        // Dersom folk har trykt på knappen i menyen trenger vi ikke vise et ekstra GUI for å vise de en ekstra knapp...
        $app_id = $this->getParameter('facebook_client_id');
        return new RedirectResponse("https://www.facebook.com/dialog/oauth?client_id=".$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email');
    }

}
