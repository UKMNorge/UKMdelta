<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use monstring;
use monstringer;
use postnummer_monstring;
use kommune_monstring;
use innslag;
use tittel;
use Exception;
use DateTime;
use stdClass;

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
        require_once('UKM/monstring.class.php');
        $view_data['translationDomain'] = 'innslag';
        
        $season = $this->container->get('ukm_delta.season')->getActive();

        $monstringer = new monstringer($season);
        // Inkluderer testkommunene hvis environment == test
        $liste = $monstringer->alle_kommuner_med_lokalmonstringer( $this->container->get( 'kernel' )->getEnvironment() == 'test' );


        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['monstringsliste'] = $liste;
        
        // Hent brukerens nærmeste lokalmønstring basert på postnummer
        require_once('UKM/monstring_tidligere.class.php');
        $monstring = false;
        $postalcode = $view_data['user']->getPostNumber();
        $pl = new postnummer_monstring( $postalcode, $season );
        $pl = $pl->monstring_get();
        if( false != $pl ) {
	        $monstring = new stdClass();
			$monstring->id = $pl->get('pl_id');
			$monstring->name = $pl->get('pl_name');
			$monstring->fylke = $pl->get('fylke_id');
			$monstring->kommuner = array();
            // $monstring->frist1 = $pl->subscribable('pl_deadline');
            // $monstring->frist2 = $pl->subscribable('pl_deadline2');
			foreach( $pl->get('kommuner') as $kommune ) {
				$monstring->kommuner[ $kommune['id'] ] = $kommune['name'];
				$monstring->k_id = $kommune['id']; // Sett denne i tilfelle det er lokalmønstring
			}
			if( sizeof( $monstring->kommuner ) > 1 ) {
				$monstring->fellesmonstring = true;
				$monstring->k_id = false;
			} else {
				$monstring->fellesmonstring = false;
			}
            $view_data['lokalt_fylke'] = new monstring($monstring->fylke);
		}

        
        $view_data['lokal_monstring'] = $monstring;
        

        //var_dump($liste[1]);
        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    public function typeAction($k_id, $pl_id)
    {
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id);
        require_once('UKM/monstring.class.php');

        // Hent lister om hvilke typer som er tillatt på denne mønstringen.
        $pl = new monstring($pl_id);
        if( !$pl->subscribable() && !$pl->subscribable('pl_deadline2') ) {
			throw new Exception('Påmeldingsfristen er ute!');		
		}
        $typeListe = $pl->getAllBandTypesDetailedNew();
        
        $view_data['typer'] = $typeListe;
		$view_data['pl'] = $pl;
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        return $this->render('UKMDeltaBundle:Innslag:type.html.twig', $view_data);
    }

    public function whoAction($k_id, $pl_id, $type, $translationDomain)
    {   
        $view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type);
        
    	$view_data['translationDomain'] = $translationDomain;
        //$view_data['translationDomain'] = 'innslag';

    	return $this->render('UKMDeltaBundle:Innslag:who'. ($this->_tittellos( $type ) ? '_tittellos':'') .'.html.twig', $view_data );
    }
    
/*    public function who_tittellosAction($k_id, $pl_id, $type, $translationDomain)
    {   
        return $this->whoAction( $k_id, $pl_id, $type, $translationDomain, true);
    }
*/
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

        // var_dump($p_id);
        // var_dump($b_id);
        // var_dump($person);

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

    public function create_tittellosAction($k_id, $pl_id, $type ) {
		return $this->createAction($k_id, $pl_id, $type, 'alene');
	}
    public function createAction($k_id, $pl_id, $type, $hvem) {
    	require_once('UKM/innslag.class.php');
    	require_once('UKM/monstring.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'hvem' => $hvem);

    	$user = $this->get('ukm_user')->getCurrentUser();
        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');
		
		$deadline = 'pl_deadline'. ($this->_tittellos( $type ) ? '2' : '');

		if( in_array($type, array('nettredaksjon','arrangor','konferansier') ) ) {
			$deadline = 'pl_deadline2';
		} else {
			$deadline = 'pl_deadline';
		}
		$monstring = new monstring( $pl_id );
		if( !$monstring->subscribable( $deadline ) ) {
            throw new Exception('Påmeldingsfristen er ute!');
        }

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
            $personService->lagreEpost($p_id, $pl_id, $user->getEmail());
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

		if( $this->_tittellos( $type ) ) {
			$meldt_pa_som_type = $innslagService->hentInnslagFraType($type, $pl_id, $person->get('p_id'));
			if( $meldt_pa_som_type ) {
				$view_data['b_id'] = $meldt_pa_som_type->get('b_id');
				return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
			}
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
            case 'matkultur':   $form = 'smartukm_titles_other';        break;
            default:            $form = 'smartukm_titles_scene';        break;
        }

       // $tittel = new tittel(false, $form);

//        $view_data['tittel'] = false;

        switch($type) {
            case 'musikk':		return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
            case 'dans':		return $this->render('UKMDeltaBundle:Dans:tittel.html.twig', $view_data);
            case 'teater':  	return $this->render('UKMDeltaBundle:Teater:tittel.html.twig', $view_data);
            case 'film':  		return $this->render('UKMDeltaBundle:Film:tittel.html.twig', $view_data);
            case 'litteratur':  return $this->render('UKMDeltaBundle:Litteratur:tittel.html.twig', $view_data);
            case 'utstilling':  return $this->render('UKMDeltaBundle:Utstilling:tittel.html.twig', $view_data);
            case 'matkultur' :  return $this->render('UKMDeltaBundle:Matkultur:tittel.html.twig', $view_data);
            default:    return $this->render('UKMDeltaBundle:Annet:tittel.html.twig', $view_data);
        }
    }

    public function editTitleAction($k_id, $pl_id, $type, $b_id, $t_id) {
        require_once('UKM/tittel.class.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id, 't_id' => $t_id);
        $innslagService = $this->get('ukm_api.innslag');
        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            case 'matkultur':   $form = 'smartukm_titles_other';        break;
            default:            $form = 'smartukm_titles_scene';        break;
        }

        $tittel = new tittel($t_id, $form);

        $view_data['tittel'] = $tittel;
        $view_data['innslag'] = $innslagService->hent($b_id);
        $view_data['translationDomain'] = $type;
		switch( $form ) {
			case 'smartukm_titles_scene':
		        $view_data['selvlaget'] = $tittel->get('selvlaget');
		        $view_data['instrumental'] = $tittel->get('instrumental');
		        switch($type) {
		            case 'musikk':  	return $this->render('UKMDeltaBundle:Musikk:tittel.html.twig', $view_data);
		            case 'dans':  	  	return $this->render('UKMDeltaBundle:Dans:tittel.html.twig', $view_data);
		            case 'teater':  	return $this->render('UKMDeltaBundle:Teater:tittel.html.twig', $view_data);
		            case 'litteratur':  return $this->render('UKMDeltaBundle:Litteratur:tittel.html.twig', $view_data);
		            default:    return $this->render('UKMDeltaBundle:Annet:tittel.html.twig', $view_data);
		        }
		        break;
		    case 'smartukm_titles_exhibition':
		    	return $this->render('UKMDeltaBundle:Utstilling:tittel.html.twig', $view_data);
			case 'smartukm_titles_video':
				return $this->render('UKMDeltaBundle:Film:tittel.html.twig', $view_data);
            case 'smartukm_titles_other':
                switch ($type) {
                    case 'matkultur': return $this->render('UKMDeltaBundle:Matkultur:tittel.html.twig', $view_data);
                    default: return $this->render('UKMDeltaBundle:Annet:tittel.html.twig', $view_data);
                }
                
		}
    }

    public function saveTitleAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/tittel.class.php');

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type,'b_id' => $b_id);
        $request = Request::createFromGlobals();

        $seasonService = $this->get('ukm_delta.season');
		switch( $type ) {
			case 'film':		$form = 'smartukm_titles_video';		break;
			case 'utstilling':	$form = 'smartukm_titles_exhibition';	break;
            case 'matkultur':   $form = 'smartukm_titles_other';        break;
			default:			$form = 'smartukm_titles_scene';		break;
		}

		// Opprett tittel-objektet og sett tittel navn
        $t_id = $request->request->get('t_id');

        if ($t_id == 'new') {
            // Create empty object
		    $tittel = new tittel(false, $form);
            $tittel->create( $b_id );
        }
        else {
            // Create object with data
            $tittel = new tittel($t_id, $form);
        }
		
		// Sett standard-felter
        if ($form != 'smartukm_titles_other') {
            $tittel->set('tittel', $request->request->get('tittel') );		
            $tittel->set('season', $seasonService->getActive() );
        }
    	
    	// Switch på de forskjellige tabellene
    	switch( $form ) {
	    	case 'smartukm_titles_scene':
				$tittel->set('varighet', $request->request->get('lengde'));	

				// Sett felter basert på type (i scene-tabellen)
		        switch ($type) {
		            case 'musikk':
						$instrumental = $request->request->get('sangtype') == 'instrumental' ? 1 : 0;		                
		                $tittel->set('instrumental', $instrumental);
		                // Musikk fortsetter inn i teater (no break)
		            case 'teater':
		                $tittel->set('melodi_av', $request->request->get('melodiforfatter'));		
		                $tittel->set('selvlaget', $request->request->get('selvlaget'));
		                
		                // I teater skal tekstforfatter alltid lagres
		                // For musikk skal tekstforfatter kun lagres hvis det 
		                // ikke er en instrumental
						if( $type == 'teater' || ( isset($instrumental) && !$instrumental) ) {
							$tittel->set('tekst_av', $request->request->get('tekstforfatter'));
						} else {
		                    $tittel->set('tekst_av', '');   
		                }
		                break;
		            case 'dans':
			            $tittel->set('koreografi', $request->request->get('koreografi'));
			            #$tittel->set('melodi_av', $request->request->get('melodiforfatter'));
		                break;
		            case 'litteratur':
						$tittel->set('tekst_av', $request->request->get('tekstforfatter'));

		                $lese_opp = $request->request->get('leseopp');
						$tittel->set('litterature_read', $lese_opp);
						if( $lese_opp == ('0' || 0) ) {
			                $tittel->set('varighet', 0);
						}
						break;
		        }
				break;
	    	case 'smartukm_titles_exhibition':
	            $tittel->set('type', $request->request->get('type'));
	            $tittel->set('beskrivelse', $request->request->get('beskrivelse'));
	    		break;
			case 'smartukm_titles_video':
				$tittel->set('varighet', $request->request->get('lengde'));	
				break;
            case 'smartukm_titles_other':
                $tittel->set('tittel', $request->request->get('tittel'));
                $tittel->set('erfaring', $request->request->get('erfaring'));
                $tittel->set('kommentar', $request->request->get('kommentar'));
                break;
    	}

		// Lagre tittel
		$tittel->lagre();
        return $this->redirectToRoute('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
    }

    public function deleteTitleAction($k_id, $pl_id, $type, $b_id, $t_id) {
        require_once('UKM/tittel.class.php');
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type,'b_id' => $b_id, 't_id' => $t_id);
        
        // Sjekker tilgangsrettigheter på innslaget, rett band-type og at påmeldingsfristen ikke er ute.
        $innslagService = $this->get('ukm_api.innslag');
        $innslagService->sjekk($b_id, $type);
        // Trenger ikke sjekke tittelen, fordi SQLdel inkluderer WHERE b_id.

        switch( $type ) {
            case 'film':        $form = 'smartukm_titles_video';        break;
            case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
            case 'matkultur':   $form = 'smartukm_titles_other';        break;
            default:            $form = 'smartukm_titles_scene';        break;
        }
        $tittel = new tittel($t_id, $form);
        $deleted = $tittel->delete();
        // Slett tittel fra innslaget
        if ($deleted) {
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

        // Sjekk tilgang og rett bandtype
        $innslagService->sjekk($b_id, $type);       
        $innslag = $innslagService->hent($b_id);

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

		// Hent beskrivelse av innslaget
        $view_data['beskrivelse'] = $innslag->get('b_description');

        $view_data['translationDomain'] = $type;
        $view_data['user'] = $user;
        $view_data['innslag'] = $innslag;
        $view_data['personer'] = $personer;
        
        if( $this->_tittellos( $type ) ) {
	        switch( $type ) {
		        case 'konferansier':
		        break;
		        case 'nettredaksjon':
		        	$view_data['funksjoner'] = array('tekst','foto','videoreportasjer','flerkamera_regi','flerkamera_kamera','pr');
					$tittellos_person = $this->_hent_tittellos_person( $b_id );					
					$view_data['valgte_funksjoner'] = json_decode( $tittellos_person->get('instrument_object') );
		        	break;
		        case 'arrangor':
		       		$view_data['funksjoner'] = array('lyd','lys','scenearbeider','artistvert','info','koordinator');
					$tittellos_person = $this->_hent_tittellos_person( $b_id );					
					$view_data['valgte_funksjoner'] = json_decode( $tittellos_person->get('instrument_object') );
		        	break;
	        }
	        return $this->render('UKMDeltaBundle:Innslag:oversikt_tittellos.html.twig', $view_data);
        }
        
        // Legg data fra innslaget i variabler som kan jobbes med enklere i twig
        $teknisk = $innslag->get('td_demand');
        if (strlen($teknisk) > 220) {
            $teknisk = substr_replace($teknisk, '...', 220);
            // Dette vil ikke påvirke lagret informasjon.
        }

        $titler = $innslag->titler($pl_id); 

        // Hvis hvem-variabelen blir sendt med.
        $request = Request::createFromGlobals();
        $hvem = $request->get('hvem');
        if (!empty($hvem)) {
            $view_data['hvem'] = $hvem;
        }
        
        if ($innslag->info['b_name'] != 'Innslag uten navn') {
            $view_data['name'] = $innslag->get('b_name');
        }
        else {
            $view_data['name'] = '';
        }
        $view_data['sjanger'] = $innslag->get('b_sjanger');
        $view_data['teknisk'] = $teknisk;
        $view_data['titler'] = $titler;
        $view_data['pameldt'] = false;
        switch ($innslag->get('b_status')) {
            case 1: $view_data['pameldt'] = false;
                break;
            default:
                $view_data['pameldt'] = true;
        }

        switch ($type) {
            case 'musikk':
            case 'film':
            case 'litteratur':
            case 'dans':
            case 'teater':
            case 'annet':
                $view_data['krev_sjanger'] = true;
                // $view_data['krev_titler'] = true;
                break;
            // case 'matkultur':
            //     $view_data['krev_sjanger'] = false;
            //     $view_data['krev_titler'] = false;
            default:
                $view_data['krev_sjanger'] = false;  
        }

        // Krev tekniske behov for alle sceneinnslag, unntatt litteratur
		if( $innslag->g('bt_form') == 'smartukm_titles_scene' && !in_array($type, array('litteratur')) ) {
	    	$view_data['krev_tekniske'] = true;   
	    } else {
		    $view_data['krev_tekniske'] = false;
	    }

        
        return $this->render('UKMDeltaBundle:Innslag:oversikt.html.twig', $view_data);
    }

    public function saveOverviewAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');
        $request = Request::createFromGlobals();

        $desc = $request->request->get('beskrivelse');
		// Håndter tittelløse innslag først
		if( $this->_tittellos( $type ) ) {
	        $innslagService->lagreBeskrivelse($b_id, $desc);
	        $personService = $this->get('ukm_api.person');
	        
	        switch( $type ) {
		        case 'nettredaksjon':
		        case 'arrangor':
					$tittellos_person = $this->_hent_tittellos_person( $b_id );
					$instrument_object = $request->request->get('funksjoner');
					$funksjon = '';
					if( is_array( $instrument_object ) ) {
						foreach( $instrument_object as $current_instrument ) {
							$funksjon .= $this->get('translator')->trans('funksjon.'.$current_instrument, array(), $type).', ';
						}
						$funksjon = rtrim( $funksjon, ', ' );
					}
					
					$innslagService->lagreInstrumentTittellos($b_id, $tittellos_person->g('p_id'), $pl_id, $funksjon, $instrument_object);
		        break;
	        }
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_status', $view_data);
		}

        $path = $request->request->get('path');
        $name = $request->request->get('navn');

        
        if(($type == 'musikk') || ($type == 'litteratur') || ($type == 'film') || ($type == 'annet') || ($type == 'dans') || ($type == 'teater')) {
            $genre = $request->request->get('sjanger');
            $innslagService->lagreSjanger($b_id, $genre);
        }

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

    public function removeAction($k_id, $pl_id, $type, $b_id) {
        // Output confirm-vindu
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'base';
        // Sjekk tilgang
        $innslagService = $this->get('ukm_api.innslag');

        $innslagService->sjekk($b_id, $type);
        $innslag = $innslagService->hent($b_id);
        
        // If post-request, i.e. JA-knapp.
        if ($this->getRequest()->isMethod('POST')) {
            $innslagService->meldAv($b_id, $pl_id);
            $this->addFlash('success', $this->get('translator')->trans('removeAction.fjernet', array("%name" => $innslag->get('b_name')), 'base'));
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
        // Else render
        
        $view_data['innslag'] = $innslag;
        $view_data['navn'] = $innslag->get('b_name');
        return $this->render('UKMDeltaBundle:Innslag:fjern.html.twig', $view_data);
    }

    public function statusAction($k_id, $pl_id, $type, $b_id) {
        require_once('UKM/inc/validate_innslag.inc.php');
        require_once('UKM/monstring.class.php');
        require_once('UKM/tittel.class.php');

        $route_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = $type;

        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);
        
        $status = $innslag->get('b_status');
        $innslag->get('b_status_text');

        $monstring = new monstring($pl_id);
        
        $frist = new DateTime();
        $frist->setTimestamp($monstring->get('pl_deadline'));
		$validering = $innslagService->hentAdvarsler($b_id, $pl_id);
        //var_dump($validering);
        $view_data['status'] = $validering[0];
        // var_dump($view_data['status']);
        // echo '<br>';
        $innslag = $innslagService->hent($b_id);
        // var_dump($innslag->get('b_status'));
        // echo '<br>';
        // var_dump($innslag);
        // die();
        $view_data['grunner'] = $validering[1];
        $personService = $this->get('ukm_api.person');
        if (array_key_exists('personer', $validering[1]) && is_array($validering[1]['personer'])) {
            foreach($validering[1]['personer'] as $pers) {
                $view_data['pers'][$pers[0]] = $personService->hent($pers[0]);
            }    
        }
        
        if (array_key_exists('titler', $validering[1]) && is_array($validering[1]['titler'])) {
            switch( $type ) {
                case 'film':        $form = 'smartukm_titles_video';        break;
                case 'utstilling':  $form = 'smartukm_titles_exhibition';   break;
                default:            $form = 'smartukm_titles_scene';        break;
            }
            foreach($validering[1]['titler'] as $tittel) {
                $view_data['t'][$tittel[0]] = $tittel = new tittel($tittel[0], $form);
            }
        }

        //var_dump($view_data['grunner']);
        $view_data['frist'] = $frist;
        $view_data['innslag'] = $innslag;
        $view_data['status_real'] = $status;

        // var_dump($view_data['grunner']);
        // var_dump($view_data['t']);
        // Oppdater status på innslaget! 
        // ValidateBand2 tar seg av status-oppdateringen??
        if($view_data['status'] == 8) {
            //$innslagService->lagreStatus($b_id, 8);
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding_pameldt', $route_data);
        }
        else {
            //$innslagService->lagreStatus($b_id, 1); // lagre en ikke-ferdig-status
            return $this->render('UKMDeltaBundle:Innslag:status.html.twig', array_merge( $route_data, $view_data ) );
        }
    }

    public function attendingAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'innslag';

        require_once('UKM/monstring.class.php');
        $monstring = new monstring($pl_id);

        $start = new DateTime();
        $start->setTimestamp($monstring->get('pl_start'));

        $name = $monstring->get('pl_name');


        // Tekst som deles på facebook!
        $view_data['fb_share_caption'] = $this->get('translator')->trans('fb_share', array('%monstring' => $name), 'base');

        $view_data['pl_navn'] = $name;

        $pl_start['dag'] = $start->format("d");
        $pl_start['maned'] = $start->format("F");
        $pl_start['time'] = $start->format("G");
        $pl_start['minutt'] = $start->format("i");
        $view_data['pl_start'] = $pl_start;

        return $this->render('UKMDeltaBundle:Innslag:pameldt.html.twig', $view_data);
    }   

    public function fristAction($k_id, $pl_id, $type, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'type' => $type, 'b_id' => $b_id);
        $view_data['translationDomain'] = 'base';

        $innslagService = $this->get('ukm_api.innslag');
        $view_data['innslag'] = $innslagService->hent($b_id);
        return $this->render('UKMDeltaBundle:Innslag:frist.html.twig', $view_data);
    }
    
    private function _tittellos( $type ) {
	    return in_array($type, array('nettredaksjon','arrangor','konferansier'));
    }
    private function _hent_tittellos_person( $b_id ) {
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');
		$innslag = $innslagService->hent( $b_id );
		$personer = $innslag->personer();
		$p_id = $personer[0]['p_id'];
		return $personService->hent( $p_id, $b_id );
    }
}
