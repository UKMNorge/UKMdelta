<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use UKMNorge\DeltaBundle\seasonService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use innslag;
use person;
use monstring;
use monstringer;
use Exception;
use Request;
use SQL;
use SQLins;
use stdClass;

require_once('UKM/innslag.class.php');

class InnslagService {
	
	public function __construct(ContainerInterface $container) {
		$this->container = $container;
	}

	public function opprett($k_id, $pl_id, $type, $hvem, $person, $userID) {
    	$bandtypeid = getBandtypeId($type);
		$seasonService = $this->container->get('ukm_delta.season');

		// Opprett en innslagsID for typen, sesongen, mønstringsid, kommuneid og send med personobjektet som er kontaktperson
   		$innslagsID = create_innslag($bandtypeid, $seasonService->getActive(), $pl_id, $k_id, $person);
    	$innslag = new innslag($innslagsID, false);

    	// Sett variabler i innslagsobjektet
    	// Risky måte å gjøre det på: én stavefeil i $innslag->set vil føre til mysql-feil og ingenting lagres. Burde oppdateres?
    	if ($hvem == 'alene') {
            //echo 'alene!';
    		$innslag->set('b_name', $person->get('p_firstname') . ' ' . $person->get('p_lastname'));
    	}
        else {
            $innslag->set('b_name', ''); 
        }
    	if ($hvem != 'kontakt') {
    		$innslag->addPerson($person->get('p_id'));	
    	}

        $innslag->set('bt_id', $bandtypeid);
		$innslag->set('b_status', 1);
		$innslag->set('b_subscr_time', time());
		if($bandtypeid == 1) {
			$innslag->set('b_kategori', $type);
		}
		$innslag->set('b_kommune', $k_id);
		$innslag->set('b_validatedby', $person->get('p_phone'));
		$innslag->set('b_password', 'delta_'.$userID);

    	$innslag->personer(); // Forces an update of personer-array in object.
    	$innslag->lagre();

    	// Legg til en exception her hvis innslag-lagring feiler?
    	return $innslag;
	}

	public function hent($innslagsID) {
		$innslag = new innslag($innslagsID, false); // False fordi b_status ikke skal trenge å være 8.
		$innslag->personer(); // Tving en oppdatering av personer-arrrayet.

		// get kjører en UTF8-encode på alle felt. Så droppe det på vei inn?

		$this->sjekkTilgang($innslagsID);

		return $innslag;		
	}

	public function meldAv($b_id, $pl_id) {
		// Sjekk rettigheter og frister
		$this->sjekkFrist($b_id);
		$this->sjekkTilgang($b_id);

		$user = $this->container->get('ukm_user')->getCurrentUser();
		$p_id = $user->getPameldUser();

		$innslag = $this->hent($b_id);
		$innslag->delete('delta', $p_id, $pl_id);
	}

	public function hentInnslagFraType($type, $pl_id, $person_id) {
		$alle_innslag = $this->hentInnslagFraKontaktperson( $person_id, null );
		foreach( $alle_innslag as $key => $gruppe ) {
			foreach( $gruppe as $innslag ) {
				if( $innslag->type == $type ) {
					// Viktig at man kan melde seg på samme kategori flere steder, men én gang per sted
					if( $innslag->innslag->min_lokalmonstring()->get('pl_id') == $pl_id ) {
						return $this->hent( $innslag->innslag->g('b_id') );
					}
				}
			}
		}
		return false;
	}
	public function hentInnslagFraKontaktperson($contact_id, $user_id) {
		$innslag_etter_status = array( 'fullstendig'=>array(), 'ufullstendig'=>array() );
		$seasonService = $this->container->get('ukm_delta.season');
		// Søk etter innslag i databasen?
		if (empty($contact_id)) {
			$qry = new SQL("SELECT `smartukm_band`.`b_id`, 
								   `smartukm_band`.`b_status`,
								   `smartukm_band`.`bt_id`, 
								   `smartukm_band`.`b_kategori` 
							FROM `smartukm_band` 
							WHERE `b_password` = 'delta_#user_id' 
							AND `b_season` = '#season'
							AND `b_status` < 9",
						array('user_id' => $user_id, 'season' => $seasonService->getActive()));
		}
		else {
			$qry = new SQL("SELECT `smartukm_band`.`b_id`,
								   `smartukm_band`.`b_status`,
									`smartukm_band`.`bt_id`, 
									`smartukm_band`.`b_kategori` 
							FROM `smartukm_band` 
							WHERE (`b_contact` = '#c_id' OR `b_password` = 'delta_#user_id') 
							AND `b_season` = '#season'
							AND `b_status` < 9", 
						array('c_id' => $contact_id, 'user_id' => $user_id, 'season' => $seasonService->getActive()));
		}

		$res = $qry->run();
		while($row = mysql_fetch_assoc($res)) {
			$innslag = new stdClass();
			if ($row['bt_id'] == 1) {
				$innslag->type = $row['b_kategori']; 
				$innslag->id = $row['b_id'];
			}
			else {
				$innslag->type = getBandTypeFromID($row['bt_id']);
				// Finpuss for routing
				if ($innslag->type == 'video') {
					$innslag->type = 'film';
				}
			}

			$innslag->innslag = new innslag($row['b_id'], false);			
			$innslag_etter_status[ $row['b_status'] == 8 ? 'fullstendig' : 'ufullstendig' ][] = $innslag;
		}
		// var_dump($innslag_etter_status);
		return $innslag_etter_status;
	}

	public function getBandType($b_id) {
		// $innslagsID er b_id. $person er personid eller personobjekt?
		$innslag = new innslag($innslagsID, false); // False fordi b_status ikke skal trenge å være 8.

		return getBandTypeFromID($innslag->get('bt_id'));
	}

	public function leggTilPerson($innslagsID, $personID) {
		// $innslagsID er b_id. $person er personid eller personobjekt?
		$innslag = new innslag($innslagsID, false); // False fordi b_status ikke skal trenge å være 8.
		
		$this->sjekkTilgang($innslagsID);
		$innslag->addPerson($personID);		
	}

	public function fjernPerson($innslagsID, $personID) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->get('pl_id');
		$innslag = new innslag($innslagsID, false);

		$this->sjekkTilgang($innslagsID);

		$innslag->removePerson($personID, 'delta', $user->getPameldUser(), $pl_id);
		
	}

	public function lagreInstrument($innslagsID, $personID, $pl_id, $instrument) {
		$innslag = new innslag($innslagsID, false);
		$person = new person($personID, $innslagsID);
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$person->set('instrument', $instrument);
		$person->set('b_id', $innslagsID); // Settes for at instrumentlagring skal funke.
		$person->lagre('delta', $user->getId(), $pl_id);
	}


	public function lagreInstrumentTittellos($innslagsID, $personID, $pl_id, $instrument, $instrument_object) {
		$innslag = new innslag($innslagsID, false);
		$person = new person($personID, $innslagsID);
		
		$user = $this->container->get('ukm_user')->getCurrentUser();
		
		$person->set('instrument', $instrument);
		$person->set('instrument_object', json_encode( $instrument_object ) );
		$person->set('b_id', $innslagsID); // Settes for at instrumentlagring skal funke.
		$person->lagre('delta', $user->getPameldUser(), $pl_id);
	}
	
	public function lagreBeskrivelse($innslagsID, $beskrivelse) {
		$innslag = new innslag($innslagsID, false);
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->get('pl_id');

		if ( $innslag->get('b_description') != utf8_encode($beskrivelse)) {
	        $innslag->set('b_description', $beskrivelse);
	        $innslag->set('td_konferansier', $beskrivelse); // Hvorfor lagrer ikke denne?
	    	$innslag->lagre('delta', $user->getPameldUser(), $pl_id);
	    }
	}	

	public function lagreArtistnavn($innslagsID, $artistnavn) {
		$innslag = new innslag($innslagsID, false);
		
		$this->sjekkTilgang($innslagsID);
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->get('pl_id');

		if ( $innslag->get('b_name') != utf8_encode($artistnavn)) {
	        $innslag->set('b_name', $artistnavn);
	    	$innslag->lagre('delta', $user->getPameldUser(), $pl_id);
	    }
	}	

	public function lagreStatus($innslagsID, $b_status) {
		$innslag = new innslag($innslagsID, false);

		$this->sjekkTilgang($innslagsID);
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->get('pl_id');

		if ( $innslag->get('b_status') != $b_status) {
			$innslag->set('b_status', $b_status);
			$innslag->lagre('delta', $user->getPameldUser(), $pl_id);
		}
	}

	public function lagreSjanger($innslagsID, $sjanger) {
		$innslag = new innslag($innslagsID, false);
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->info['pl_id'];
		//var_dump($pl_id);
		// var_dump($teknisk);
		
		$this->sjekkTilgang($innslagsID);
		$innslag->set('b_sjanger', $sjanger);
	   	$innslag->lagre('delta', $user->getPameldUser(), $pl_id);
	}

	public function lagreTekniskeBehov($innslagsID, $teknisk) {
		$innslag = new innslag($innslagsID, false);
		// var_dump($teknisk);
		$this->sjekkTilgang($innslagsID);

		$user = $this->container->get('ukm_user')->getCurrentUser();
		$pl_id = $innslag->min_lokalmonstring()->get('pl_id');

		$innslag->set('td_demand', $teknisk);
	   	$innslag->lagre('delta', $user->getPameldUser(), $pl_id);
	}

	public function hentAdvarsler($innslagsID, $pl_id) {
		$innslag = new innslag($innslagsID, false);
		
		$this->sjekkTilgang($innslagsID);

		$validate = $innslag->validateBand2($innslagsID);
       	//var_dump($validate);

		// $warnings = $innslag->warning_array($pl_id);
		// $warnings = $this->_warningToText($warnings);

		return $validate;
	}

	private function _warningToText($warnings) {
		$output = array();
		//var_dump($warnings);
		foreach ($warnings as $warning) {
			if ($warning == 'innslaget har ingen titler (og vil derfor ikke kunne settes opp i et program)') {
				$output[] = 'lat';
			}
			elseif ($warning == 'innslaget har en total varighet p&aring; 0 sek (mindre enn 10 sekunder)') {
				$output[] = 'varighet';
			}
			elseif ($warning == 'innslaget har ingen tekniske behov') {
				$output[] = 'teknisk';
			}
			elseif ($warning == 'innslaget har ingen deltakere') {
				$output[] = 'ingendeltakere';
			}
		}

		$pos = array_search('varighet', $output);
		if (!($pos === FALSE)) {
			if (in_array('lat', $output)) {
				// Hvis lat finnes i arrayet, fjern "varighet"
				unset($output[$pos]);
				$output = array_values($output);
			}
		}
		return $output;
	}

	####
	# SjekkTilgang
	# Funksjonen sjekker om personen som prøver å gjøre endringer har tilgang til innslaget.
	# Hvis ikke kaster den en exception med kode 0 og teksten 'ingentilgang'.
	###
	//TODO:  ikke gjør sjekken dersom innslaget ikke finnes??
	public function sjekkTilgang($b_id) {

		$user = $this->container->get('ukm_user')->getCurrentUser();

		$u_id = $user->getId();
		$p_id = $user->getPameldUser(); 

		$innslag = new innslag($b_id, false);

		if (($innslag->get('b_password') != 'delta_'.$u_id) && ($innslag->get('b_contact') != $p_id) ) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

	}

	####
	# SjekkBandtype
	# Funksjonen sjekker om bandtypen som er registrert stemmer med URL'en man forsøker å åpne
	# Mest for å forhindre trøbbel i databasen med forskjellige felt brukt samtidig
	# Ved trøbbel kaster den en Exception med kode 0 og teksten 'feilbandtype'.
	public function sjekkBandtype($b_id, $type) {
		$innslag = new innslag($b_id, false);

		$bandtype = getBandTypeFromID($innslag->get('bt_id'));

		if ($bandtype == 'scene') {
			// Scene
			$bandtype = $innslag->get('b_kategori');
		}
		elseif ($bandtype == 'video') {
			$bandtype = 'film';
		}

		if ($bandtype != $type) {
			#throw new Exception('feilbandtype');
			// Redirect til rett type om vi kan?
			$route = $this->container->get('request')->get('_route');
			#var_dump($route);
			$view_data['k_id'] = $this->container->get('request')->get('k_id');
			$view_data['pl_id'] = $this->container->get('request')->get('pl_id');
			$view_data['type'] = $bandtype; # Sett korrekt type for innslaget

			$view_data['b_id'] = $b_id;
			#$view_data['type'] = $this->container->get('request')->get('type');
			#$view_data['b_id'] = $this->container->get('request')->get('b_id');
			#var_dump($view_data);
			$path = $this->container->get('router')->generate($route, $view_data, 301);
			echo new RedirectResponse($path);

			// Stop execution somehow.
			throw new Exception('Feil kategori for innslaget! Vi videresender deg nå.');
		}
	}

	### Sjekk
	# Funksjonen kjører sjekkTilgang, SjekkBandtype og andre sjekkfunksjoner.
	# Returnerer ingen ting
	public function sjekk($b_id, $type) {
		$this->sjekkTilgang($b_id);
		$this->sjekkBandtype($b_id, $type);
		if (!$this->sjekkFrist($b_id)) {
			// Kast Exception for alle sider som ikke bruker sjekkFrist direkte.
			throw new Exception('Påmeldingsfristen er ute!');
		}
	}

	### Sjekk
	# Funksjonen sjekker om fristen for å melde på innslag til mønstringen er ute.
	# Hvis den er det returnerer den false, hvis ikke true.
	public function sjekkFrist($b_id) {
		$innslag = $this->hent($b_id);
		$pl = $innslag->min_lokalmonstring();
		
		if( $innslag->tittellos() ) {
			return $pl->subscribable( 'pl_deadline2' );
		}
		return $pl->subscribable( 'pl_deadline' );
	}
}

?>