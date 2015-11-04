<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use monstring;
use monstringer;
use innslag;
use tittel;
use Exception;

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

    	return $this->render('UKMDeltaBundle:Innslag:who.html.twig', $view_data );
    }

    public function newPersonAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        
        return $this->render('UKMDeltaBundle:Innslag:nyPerson.html.twig', $view_data);
        

    }

    public function editPersonAction($k_id, $pl_id, $type, $b_id, $p_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 'p_id' => $p_id);

        // TODO: Hent data fra database (PersonService), ikke UserBundle

        $user = $this->get('ukm_user')->getCurrentUser();
        $personService = $this->get('ukm_api.person');
        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);
        $person = $personService->hent($p_id);
        foreach($innslag->personer() as $personInfo) {
            // Send kun den personinfoen som stemmer med den personen man vil redigere
            if ($personInfo['p_id'] == $p_id) {
                $innslagsPerson = $personInfo;
            }
        }
        // var_dump($person);
        // var_dump($innslagsPerson);

        $view_data['user'] = $user;
        $view_data['person'] = $person;
        $view_data['innslag'] = $innslagsPerson;
        $view_data['age'] = $personService->alder($person);
        return $this->render('UKMDeltaBundle:Innslag:redigerPerson.html.twig', $view_data);
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

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
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

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function removePersonAction($k_id, $pl_id, $type, $b_id, $p_id) {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 'p_id' => $p_id);
        $innslagService = $this->get('ukm_api.innslag');
        
        $innslagService->fjernPerson($b_id, $p_id);

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function createAction($k_id, $pl_id, $type, $hvem) 
    {
    	require_once('UKM/innslag.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type);

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
    	return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function newTitleAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/tittel.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');

        $view_data['innslag'] = $innslagService->hent($b_id);

        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            default:            $form = 'smartukm_titles_scene';        break;
        }

        $tittel = new tittel(false, $form);

        $view_data['tittel'] = $tittel;

        if ($type == 'musikk') {
            return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);   
        }
        elseif ($type == 'dans') {

        }
        else {
            return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
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

        return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
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

		// Sett felter for musikk
        if ($type == "musikk") {
	        $lengde = $request->request->get('lengde'); // I sekunder
            $sangtype = $request->request->get('sangtype');
            $selvlaget = $request->request->get('selvlaget');
            $tekstforfatter = $request->request->get('tekstforfatter');
            $melodiforfatter = $request->request->get('melodiforfatter');
            
            $tittel->set('tekst_av', $tekstforfatter);
            $tittel->set('melodi_av', $melodiforfatter);
            $tittel->set('varighet', $lengde);
        }
        // Sett felter for dans
        elseif ($type == "dans") {
	        $lengde = $request->request->get('lengde'); // I sekunder
            $koreografi = $request->request->get('koreografi');
            
            $tittel->set('koreografi', $koreografi);
            $tittel->set('varighet', $lengde);
        }
  
		// Lagre tittel
		$tittel->lagre();
        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
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
            return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);    
        }
        // TODO: Feilmelding her!
        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_tittel', $view_data);
        
    }

    public function technicalAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'innslag';
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

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_innslag_oversikt', $view_data);
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
            $person['age'] = $personService->alder($personService->hent($person['p_id']));
        }
        $titler = $innslag->titler($pl_id); 

        #var_dump($personer);
        #var_dump($innslag); 

        $view_data['translationDomain'] = 'musikk';
        $view_data['user'] = $user;
        if ($innslag->info['b_name'] != 'Innslag uten navn') {
            $view_data['name'] = $innslag->info['b_name'];  
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

        // Sjekk om path er en route?
        return $this->redirectToRoute($path, $view_data);
       
    }

    public function statusAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/inc/validate_innslag.inc.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'innslag';

        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);

        $innslag->get('b_status');
        $innslag->get('b_status_text');

        $frist = array('maned' => 2, 'dag' => 'sistefrist-dag', 'time' => 12, 'minutt' => 0);   

        $view_data['grunner'] = $innslagService->hentAdvarsler($b_id, $pl_id);
        //var_dump($view_data['grunner']);
        $view_data['frist'] = $frist;

        // Oppdater status på innslaget!
        if(empty($view_data['grunner'])) {
            $innslagService->lagreStatus($b_id, 8);
        }
     
        if ($innslag->get('b_status') != 8) {
            return $this->render('UKMDeltaBundle:Innslag:status.html.twig', $view_data);
        }
        else { // Innslaget er ferdig påmeldt!
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_pameldt', $view_data);
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