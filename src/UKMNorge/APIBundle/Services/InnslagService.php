<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use UKMNorge\DeltaBundle\seasonService;
use innslag;
use person;
use monstring;
use monstringer;
use Exception;
use Request;
use SQL;
use SQLins;

require_once('UKM/innslag.class.php');

class InnslagService {
	
	public function __construct($container) {
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

		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}
		else {
			return $innslag;
		}		
	}

	public function hentInnslagFraKontaktperson($contact_id, $user_id) {
		$innslag = array();
		$seasonService = $this->container->get('ukm_delta.season');
		// Søk etter innslag i databasen?
		if (empty($contact_id)) {
			$qry = new SQL("SELECT `smartukm_band`.`b_id`, `smartukm_technical`.`pl_id`, `smartukm_band`.`bt_id`, `smartukm_band`.`b_kategori` FROM `smartukm_band` LEFT JOIN `smartukm_technical` ON `smartukm_band`.`b_id` = `smartukm_technical`.`b_id` WHERE `b_password` = 'delta_#user_id' AND `b_season` = '#season'", array('user_id' => $user_id, 'season' => $seasonService->getActive()));
		}
		else {
			$qry = new SQL("SELECT `smartukm_band`.`b_id`, `smartukm_technical`.`pl_id`, `smartukm_band`.`bt_id`, `smartukm_band`.`b_kategori` FROM `smartukm_band` LEFT JOIN `smartukm_technical` ON `smartukm_band`.`b_id` = `smartukm_technical`.`b_id` WHERE (`b_contact` = '#c_id' OR `b_password` = 'delta_#user_id') AND `b_season` = '#season'", array('c_id' => $contact_id, 'user_id' => $user_id, 'season' => $seasonService->getActive()));
		}

		$res = $qry->run();
		while($row = mysql_fetch_assoc($res)) {
			#$dump[] = $row;
			if ($row['bt_id'] == 1) {
				$type = $row['b_kategori']; 
			}
			else {
				$type = getBandTypeFromID($row['bt_id']);
			}

			// Finpuss for routing
			if ($type == 'video') {
				$type = 'film';
			}

			$innslag[] = array(new innslag($row['b_id'], false), $row['pl_id'], $type);
		}
		//var_dump($innslag);
		//die();
		return $innslag;
	}

	public function getBandType($b_id) {
		// $innslagsID er b_id. $person er personid eller personobjekt?
		$innslag = new innslag($innslagsID, false); // False fordi b_status ikke skal trenge å være 8.

		return getBandTypeFromID($innslag->get('bt_id'));
	}

	public function leggTilPerson($innslagsID, $personID) {
		// $innslagsID er b_id. $person er personid eller personobjekt?
		$innslag = new innslag($innslagsID, false); // False fordi b_status ikke skal trenge å være 8.
		
		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}
		else {
			$innslag->addPerson($personID);
		}		
		
	}

	public function fjernPerson($innslagsID, $personID) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$innslag = new innslag($innslagsID, false);

		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}
		else {
			$innslag->removePerson($personID);
		}	
	}

	public function lagreInstrument($innslagsID, $personID, $pl_id, $instrument) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$innslag = new innslag($innslagsID, false);
		$person = new person($personID, $innslagsID);
		
		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		#Oppdatert lagre-funksjon: 
		$person->set('instrument', $instrument);
		$person->set('b_id', $innslagsID); // Settes for at instrumentlagring skal funke.
		$person->lagre('delta', $user->getId(), $pl_id);
	}

	public function lagreBeskrivelse($innslagsID, $beskrivelse) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$innslag = new innslag($innslagsID, false);
		
		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		if ( $innslag->get('b_description') != utf8_encode($beskrivelse)) {
	        $innslag->set('b_description', utf8_encode($beskrivelse));
	        $innslag->set('td_konferansier', utf8_encode($beskrivelse)); // Hvorfor lagrer ikke denne?
	    	$innslag->lagre();
	    }
	}	

	public function lagreArtistnavn($innslagsID, $artistnavn) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$innslag = new innslag($innslagsID, false);
		
		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		if ( $innslag->get('b_name') != utf8_encode($artistnavn)) {
	        $innslag->set('b_name', $artistnavn);
	    	$innslag->lagre();
	    }
	}	

	public function lagreStatus($innslagsID, $b_status) {
		$innslag = new innslag($innslagsID, false);

		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		if ( $innslag->get('b_status') != $b_status) {
			$innslag->set('b_status', $b_status);
			$innslag->lagre();
		}
	}

	public function lagreSjanger($innslagsID, $sjanger) {
		$innslag = new innslag($innslagsID, false);
		// var_dump($teknisk);

		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		$innslag->set('b_sjanger', $sjanger);
	   	$innslag->lagre();
	}

	public function lagreTekniskeBehov($innslagsID, $teknisk) {
		$innslag = new innslag($innslagsID, false);
		// var_dump($teknisk);

		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
		if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

		$innslag->set('td_demand', $teknisk);
	   	$innslag->lagre();
	}

	public function hentAdvarsler($innslagsID, $pl_id) {
		$innslag = new innslag($innslagsID, false);
		
		// Sjekk om Symfony-brukeren matcher delta_-feltet
		$user = $this->container->get('ukm_user')->getCurrentUser();
		$u_id = $user->getId();
       	if ($innslag->get('b_password') != 'delta_'.$u_id) {
			throw new Exception('Du har ikke tilgang til dette innslaget!');
		}

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
}

?>