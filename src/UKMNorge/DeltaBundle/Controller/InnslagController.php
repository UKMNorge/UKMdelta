<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use monstring;
use monstringer;
use innslag;
use tittel;
use Exception;
use DateTime;

class InnslagController extends Controller
{
    public function pameldingAction()
    {
    	$view_data = array();

    	$view_data['user'] = $this->get('ukm_user')->getCurrentUser();
    	return $this->render('UKMDeltaBundle:Innslag:pamelding.html.twig', $view_data);
    }

    public function geoAction()
    {
        require_once('UKM/monstringer.class.php');
        $view_data['translationDomain'] = 'innslag';
        
        $season = $this->container->get('ukm_delta.season')->getActive();

        $monstringer = new monstringer($season);
        // Inkluderer testkommunene hvis environment == test
        $liste = $monstringer->alle_kommuner_med_lokalmonstringer( $this->container->get( 'kernel' )->getEnvironment() == 'test' );

        //var_dump($liste);
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['monstringsliste'] = $liste;

        //var_dump($liste[1]);
        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    public function typeAction($k_id, $pl_id)
    {
        require_once('UKM/monstring.class.php');
        // Hent lister om hvilke typer som er tillatt på denne mønstringen.
        $pl = new monstring($pl_id);
        $typeListe = $pl->getAllBandTypesDetailedNew();
        //var_dump($typeListe);
        $view_data['k_id'] = $k_id;
        $view_data['pl_id'] = $pl_id;
        $view_data['typer'] = $typeListe;
    
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        return $this->render('UKMDeltaBundle:Innslag:type.html.twig', $view_data);
    }

    public function whoAction($k_id, $pl_id, $type, $translationDomain)
    {   
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type);
        
    	$view_data['translationDomain'] = $translationDomain;
        //$view_data['translationDomain'] = 'innslag';

    	return $this->render('UKMDeltaBundle:Innslag:who.html.twig', $view_data );
    }

    /* PERSONER */
    public function newPersonAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['person'] = false;
        $view_data['translationDomain'] = $type;
        
        return $this->render('UKMDeltaBundle:Innslag:person.html.twig', $view_data);
    }

    public function editPersonAction($k_id, $pl_id, $type, $b_id, $p_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 'p_id' => $p_id);

        // TODO: Hent data fra database (PersonService), ikke UserBundle

        $user = $this->get('ukm_user')->getCurrentUser();
        $personService = $this->get('ukm_api.person');
        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);
        $person = $personService->hent($p_id, $b_id);

        $view_data['user'] = $user;
        $view_data['person'] = $person;
        $view_data['innslag'] = $innslag;
        $view_data['age'] = $personService->alder($person);
        $view_data['translationDomain'] = $type;
        return $this->render('UKMDeltaBundle:Innslag:person.html.twig', $view_data);
    }

    public function saveNewPersonAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        // Ta imot post-variabler
        $request = Request::createFromGlobals();
        
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');  

        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $alder = $request->request->get('alder');
        $instrument = $request->request->get('instrument');
        $mobil = $request->request->get('mobil');

        //echo '<br>saveNewPersonAction():<br/>';
        // var_dump($fornavn);
        // var_dump($etternavn);
        // var_dump($mobil);
        // var_dump($alder);
        // var_dump($instrument);

        // Hent personobjekt om deltakeren finnes, opprett en ny en hvis ikke.
        $person = $personService->opprett($fornavn, $etternavn, $mobil, $pl_id);
        $p_id = $person->get('p_id');

        $innslagService->leggTilPerson($b_id, $p_id);
        $innslagService->lagreInstrument($b_id, $p_id, $pl_id, $instrument);
        $personService->lagreAlder($p_id, $pl_id, $alder);
        $personService->lagreMobil($p_id, $pl_id, $mobil);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function savePersonAction($k_id, $pl_id, $type, $b_id, $p_id) {
        // Ta imot post-variabler
        $request = Request::createFromGlobals();
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);

        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');  

        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $alder = $request->request->get('alder');
        $instrument = $request->request->get('instrument');
        $mobil = $request->request->get('mobil');

        // Sjekk inputs?

        $innslagService->lagreInstrument($b_id, $p_id, $pl_id, $instrument);
        $personService->lagreFornavn($p_id, $pl_id, $fornavn);
        $personService->lagreEtternavn($p_id, $pl_id, $etternavn);
        $personService->lagreAlder($p_id, $pl_id, $alder);
        $personService->lagreMobil($p_id, $pl_id, $mobil);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function removePersonAction($k_id, $pl_id, $type, $b_id, $p_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 'p_id' => $p_id);
        $innslagService = $this->get('ukm_api.innslag');
        
        $innslagService->fjernPerson($b_id, $p_id);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function createAction($k_id, $pl_id, $type, $hvem) {
    	require_once('UKM/innslag.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'hvem' => $hvem);

    	$user = $this->get('ukm_user')->getCurrentUser();
        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Create user
            $person = $personService->opprett($user->getFirstname(), $user->getLastname(), $user->getPhone(), $pl_id);
            $p_id = $person->get('p_id');
            // Sett adresse og diverse.
            $personService->adresse($person, $user->getAddress(), $user->getPostNumber(), $user->getPostPlace(), $pl_id);
            // Sett alder basert på user-bundle-alder
            $alder = $user->getBirthdate();
            $personService->lagreAlder($p_id, $pl_id, $alder);
            // Oppdater personobjektet
            $person = $personService->hent($p_id);

            $user->setPameldUser($p_id);
            // Oppdater verdier i UserBundle
            $userManager->updateUser($user);
        }
        else {
            // Hent brukerobjektet dersom det finnes
            $person = $personService->hent($user->getPameldUser());
        }

        // Opprett et nytt innslag
        $innslag = $innslagService->opprett($k_id, $pl_id, $type, $hvem, $person, $user->getId());        

        $view_data['b_id'] = $innslag->get('b_id');
        //var_dump($hvem);

    	// var_dump($user);
    	// var_dump($person);
    	//var_dump($innslag);
    	return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function newTitleAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/tittel.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = $type;

        $innslagService = $this->get('ukm_api.innslag');

        $view_data['innslag'] = $innslagService->hent($b_id);

        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            default:            $form = 'smartukm_titles_scene';        break;
        }

       // $tittel = new tittel(false, $form);

//        $view_data['tittel'] = false;

        if ($type == 'musikk') {
            return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);   
        }
        elseif ($type == 'dans') {
            return $this->render('UKMDeltaBundle:Dans:tittel.html.twig', $view_data);
        }
        elseif ($type == 'teater') {
            return $this->render('UKMDeltaBundle:Teater:tittel.html.twig', $view_data);
        }
        elseif ($type == 'film') {
            return $this->render('UKMDeltaBundle:Film:tittel.html.twig', $view_data);
        }
        else {
            // Midlertidig, bør gjøre noe annet her.
            return $this->render('UKMDeltaBundle:Annet:tittel.html.twig', $view_data);
        }
    }

    public function editTitleAction($k_id, $pl_id, $type, $b_id, $t_id) {
        require_once('UKM/tittel.class.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 't_id' => $t_id);
        $innslagService = $this->get('ukm_api.innslag');
        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            default:            $form = 'smartukm_titles_scene';        break;
        }

        $tittel = new tittel($t_id, $form);

        $view_data['tittel'] = $tittel;
        $view_data['innslag'] = $innslagService->hent($b_id);
        $view_data['translationDomain'] = $type;

        if ($type == 'musikk') {
            return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);   
        }
        elseif ($type == 'dans') {
            return $this->render('UKMDeltaBundle:Dans:tittel.html.twig', $view_data);
        }
        elseif ($type == 'teater') {
            return $this->render('UKMDeltaBundle:Teater:tittel.html.twig', $view_data);
        }
        elseif ($type == 'film') {
            return $this->render('UKMDeltaBundle:Film:tittel.html.twig', $view_data);
        }
        else {
            // Midlertidig, bør gjøre noe annet her.
            return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
        }
    }

    public function saveTitleAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/tittel.class.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type,'b_id' => $b_id);
        $request = Request::createFromGlobals();
        $t_id = $request->request->get('t_id');

        $seasonService = $this->get('ukm_delta.season');
		switch( $type ) {
			case 'film':		$form = 'smartukm_titles_video';		break;
			case 'utstilling':	$form = 'smartukm_titles_exhibition';	break;
			default:			$form = 'smartukm_titles_scene';		break;
		}

		// Hent variabler		
        $tittelnavn = $request->request->get('tittel');
		$lengde = $request->request->get('lengde'); // I sekunder
		$season = $seasonService->getActive();
		
		// Opprett tittel-objektet og sett tittel navn
        if ($t_id == 'new') {
            // Create empty object
		    $tittel = new tittel(false, $form);
            $tittel->create( $b_id );
        }
        else {
            // Create object with data
            $tittel = new tittel($t_id, $form);
        }

    	$tittel->set('tittel', $tittelnavn );		
    	$tittel->set('season', $season );
        $tittel->set('varighet', $lengde);

		// Sett felter for musikk
        if ($type == "musikk" || $type == 'teater') {
            $sangtype = $request->request->get('sangtype');
            $selvlaget = $request->request->get('selvlaget');
            $tekstforfatter = $request->request->get('tekstforfatter');
            $melodiforfatter = $request->request->get('melodiforfatter');
            
            $tittel->set('tekst_av', $tekstforfatter);
            // var_dump(mb_detect_encoding($melodiforfatter));
            // die();
            $tittel->set('melodi_av', $melodiforfatter);
        }
        // Sett felter for dans
        elseif ($type == "dans") {
            $koreografi = $request->request->get('koreografi');            
            $melodi_av = $request->request->get('melodiforfatter');            
            $tittel->set('koreografi', $koreografi);
            $tittel->set('melodi_av', $melodi_av);
        }
  
		// Lagre tittel
		$tittel->lagre();
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function deleteTitleAction($k_id, $pl_id, $type, $b_id, $t_id) {
        require_once('UKM/tittel.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type,'b_id' => $b_id, 't_id' => $t_id);
        // Gjøre noe validering her?


        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            default:            $form = 'smartukm_titles_scene';        break;
        }
        $tittel = new tittel($t_id, $form);
       
        // Slett tittel fra innslaget
        if ($deleted = $tittel->delete()) {
	        $translated_message = $this->get('translator')->trans('tittel.slettet', array('%tittel'=>$tittel->tittel), $type);
            $this->addFlash('success', $translated_message);
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);    
        }
        
        $this->addFlash('danger', 'En feil oppsto ved sletting av tittel. Forsøk igjen, og kontakt support hvis problemet fortsetter.');
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function technicalAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = $type
        ;
        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);

        $view_data['teknisk'] = $innslag->get('td_demand');
        return $this->render('UKMDeltaBundle:Innslag:teknisk.html.twig', $view_data);
    }

    public function saveTechnicalAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
       
        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);
        $request = Request::createFromGlobals();

        $tekniskekrav = $request->request->get('teknisk');

        $innslagService->lagreTekniskeBehov($b_id, $tekniskekrav);

        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function overviewAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/innslag.class.php');

        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);

        $user = $this->get('ukm_user')->getCurrentUser();
        // Hent data om innslaget 
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');
        $innslag = $innslagService->hent($b_id);

        // Legg data fra innslaget i variabler som kan jobbes med enklere i twig
        $teknisk = $innslag->get('td_demand');
        if (strlen($teknisk) > 220) {
            $teknisk = substr_replace($teknisk, '...', 220);
            // Dette vil ikke påvirke lagret informasjon.
        }

        $personer = $innslag->personer();
        foreach ($personer as &$person) {
            $alder = $personService->alder($personService->hent($person['p_id']));
            if ($alder > 0) {
               $person['age'] = $alder; 
            }
            else {
               $person['age'] =  '25+';
            }
            
        }
        $titler = $innslag->titler($pl_id); 

        #var_dump($personer);
        #var_dump($innslag); 
        // Hvis hvem-variabelen blir sendt med.
        $request = Request::createFromGlobals();
        $hvem = $request->get('hvem');
        if (!empty($hvem)) {
            $view_data['hvem'] = $hvem;
        }
        
        $view_data['translationDomain'] = $type;
        $view_data['user'] = $user;
        if ($innslag->info['b_name'] != 'Innslag uten navn') {
            $view_data['name'] = $innslag->info['b_name'];  
        }
        else {
            $view_data['name'] = '';
        }
        $view_data['teknisk'] = $teknisk;
        $view_data['innslag'] = $innslag->info;
        $view_data['beskrivelse'] = utf8_decode($innslag->get('b_description'));
        $view_data['personer'] = $personer;
        $view_data['titler'] = $titler;

        
        return $this->render('UKMDeltaBundle:Innslag:oversikt.html.twig', $view_data);
    }

    public function saveOverviewAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');
        $request = Request::createFromGlobals();

        $path = $request->request->get('path');
        $name = $request->request->get('navn');
        $desc = $request->request->get('beskrivelse');

        $innslagService->lagreBeskrivelse($b_id, $desc);
        $innslagService->lagreArtistnavn($b_id, $name);


        // var_dump($path);
        // die();
        // Path er henta fra deltapath, som blir satt javascriptet til path generert via Twig.
        if (empty($path)) {
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
        }
        return $this->redirect($path);
        // Sjekk om path er en route?
    }

    public function statusAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/inc/validate_innslag.inc.php');
        require_once('UKM/monstring.class.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = $type;

        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);

        $innslag->get('b_status');
        $innslag->get('b_status_text');

        $monstring = new monstring($pl_id);
        
        $frist = new DateTime();
        $frist->setTimestamp($monstring->get('pl_deadline'));
        
        $view_data['grunner'] = $innslagService->hentAdvarsler($b_id, $pl_id);
        $view_data['frist'] = $frist;
        $view_data['innslag'] = $innslag;

        // Oppdater status på innslaget!
        if(empty($view_data['grunner'])) {
            $innslagService->lagreStatus($b_id, 8);
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_pameldt', $view_data);
        }
        else {
            $innslagService->lagreStatus($b_id, 1); // lagre en ikke-ferdig-status
            return $this->render('UKMDeltaBundle:Innslag:status.html.twig', $view_data);
        }
    }

    public function attendingAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'innslag';

        $pl['dag'] = 15;
        $pl['maned'] = "januar";
        $pl['time'] = 15;
        $pl['minutt'] = 30;
        $view_data['pl'] = $pl;

        return $this->render('UKMDeltaBundle:Innslag:pameldt.html.twig', $view_data);
    }   
}