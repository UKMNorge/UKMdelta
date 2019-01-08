<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use person;
use person_v2;
use DateTime;
use Exception;
use monstring_v2;
use innslag_v2;

use UKMNorge\Samtykke;

require_once('UKM/person.class.php');
use SQL;
use SQLins;

class PersonService {
	public function __construct($container) {
		$this->container = $container;
	}

	public function opprett($fornavn, $etternavn, $mobil, $pl_id) {
		// Keys i person-objektet
		// p_firstname
		// p_lastname
		// p_phone
		// echo '<br>opprett():<br/>';
		// var_dump($fornavn);
  //       var_dump($etternavn);
  //       var_dump($mobil);

		$user = $this->container->get('ukm_user')->getCurrentUser();

		// Oppretter et tomt personobjekt (Se person.class.php)
		$person = new person(false, false);

		// Sjekk om personen finnes
		$finnes = $person->getExistingPerson($fornavn, $etternavn, $mobil);

		// var_dump($finnes);
		if ($finnes)
			return $finnes;

		$person->create();
		// Oppdater verdier i person-objektet
		$person->set('p_firstname', $fornavn);
		$person->set('p_lastname', $etternavn);
		$person->set('p_phone', $mobil);
		// Send data til databasen
		// var_dump($person);
		$person->lagre('delta', $user->getId(), $pl_id);

		return $person;
	}

	public function adresse($person, $adresse, $postnummer, $poststed, $pl_id) {
		$user = $this->container->get('ukm_user')->getCurrentUser();
		
		if (!get_class($person) == 'person') {
			throw new Exception ('Kunne ikke oppdatere adresse - feil objekt mottatt. Ventet person, fikk ' . get_class($person));
		}
		
		// Sjekk lengden på poststed, padd med 0 i starten om > 4
		$postnummer = strval($postnummer);
		while (strlen($postnummer) < 4) {
			$postnummer = "0" . $postnummer;
		}
		$person->set('p_adress', $adresse);
		$person->set('p_postnumber', $postnummer);
		$person->set('p_postplace', $poststed);
		$person->lagre('delta', $user->getId(), $pl_id);
	}

	public function hent($id, $b_id=false) {
		$person = new person($id, $b_id);
		$innslagService = $this->container->get('ukm_api.innslag');

		// Hvis vi har innslaget kan vi vite om vedkommende har tilgang
		if( false != $b_id ) {
			$innslag = $innslagService->hent( $b_id );
			if( !$innslag->harPerson( $id ) ) {
				throw new Exception('Du har ikke tilgang til denne personen',20001);
			}
		}

		if (!is_numeric($person->get('p_id'))) {
			throw new Exception('Fant ikke person med id ' . $id);
		}

		$postnummer = $person->get('p_postnumber');
		while (strlen($postnummer) < 4) {
			$postnummer = '0'.$postnummer;
		}
		// Fiks for visning.
		$person->set('p_postnumber', $postnummer);
		return $person;
	}

	public function alder($person) {

		$birthdate = new DateTime();
		$birthdate->setTimestamp($person->get('p_dob'));

		if( $person->get('p_dob') == 0 ) {
			$age = '25+';			
		} else {
	        $now = new DateTime('now');
			$age = $birthdate->diff($now)->y;
		}

        // $person->set('age', $age);
        return $age;
	}

	public function lagreFornavn($personID, $pl_id, $fornavn) {
		$person = new person($personID);

		if ($person->get('p_firstname') != $fornavn) {
			// Oppdater fornavn
			$sql = new SQLins('smartukm_participant', array('p_id' => $personID));
			$sql->add('p_firstname', $fornavn);
			$sql->run();
			// Error check her?
			// Force reload av innslagsnavn om det er et alene-innslag?
		}
		else {
			// Ikke gjør noe
			return 0;
		}
	}

	public function lagreEtternavn($personID, $pl_id, $etternavn) {
		$person = new person($personID);

		if ($person->get('p_lastname') != $etternavn) {
			// Oppdater etternavn
			$sql = new SQLins('smartukm_participant', array('p_id' => $personID));
			$sql->add('p_lastname', $etternavn);
			$sql->run();
			// Error check her?
			// Force reload av innslagsnavn om det er et alene-innslag?

			// Legge til noe i flashbag elns?
		}
		else {
			// Ikke gjør noe
			return 0;
		}
	}

	public function lagreAlder($personID, $pl_id, $alder) {
		$person = new person($personID);
		$user = $this->container->get('ukm_user')->getCurrentUser();

		if (is_object($alder) && get_class($alder) == "DateTime") {
			$dob = $alder->getTimestamp();
			$alder = date("Y") - $alder->format("Y");
		}
		elseif( $alder == 0) {
			$dob = 0;
		}
		else {
			// Konverter fra alder i tall til år
			$dob = new DateTime();
			$birthyear = date("Y") - $alder;
			$dob->setDate($birthyear, 1, 1); // Setter året til fødselsåret
			$dob = $dob->getTimestamp();

		}

		if ($person->getAge() != $alder) {
			//var_dump($birthyear);
			//var_dump($dob);
			
			$person->set('p_dob', $dob);
			$person->lagre('delta', $user->getId(), $pl_id);
		}
		else {
			// Ikke gjør noe
			return 0;
		}
	}

	public function lagreMobil($personID, $pl_id, $mobil) {
		$person = new person($personID);
		$user = $this->container->get('ukm_user')->getCurrentUser();

		if ($person->get('p_phone') != $mobil) {
			$person->set('p_phone', $mobil);
			$person->lagre('delta', $user->getId(), $pl_id);
		}
	}

	public function lagreEpost($personID, $pl_id, $epost) {
		$person = new person($personID);
		$user = $this->container->get('ukm_user')->getCurrentUser();

		if ($person->get('p_email') != $epost) {
			$person->set('p_email', $epost);
			$person->lagre('delta', $user->getId(), $pl_id);
		}
	}

    /**
     * Oppdater personvern-valg for brukeren
     * Setter samme innstilling på ss3_participant-objektet som delta-brukeren
     */
    public function oppdaterPersonvern( $user, $innslagId, $monstringId ) {
        /**
         * Hvis dette skjer er funksjonen kalt på feil sted i koden,
         * men det kan jo skje.
         */
        if ($user->getPameldUser() === null) {
            return false;
        }
        $person = new person_v2( $user->getPameldUser() );
        
        require_once('UKM/samtykke/person.class.php');
        $sesong = $this->container->get('ukm_delta.season')->getActive();
        $status = $user->getSamtykke() ? 
            'godkjent' : 
            'ikke_godkjent';
        $ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? 
            $_SERVER['HTTP_CF_CONNECTING_IP'] :
            $_SERVER['REMOTE_ADDR'];

        // Hent mønstringen
        require_once('UKM/monstring.class.php');
        require_once('UKM/samtykke/person.class.php');

        $monstring = new monstring_v2( $monstringId );
        $innslag = $monstring->getInnslag()->get( $innslagId, true );

        // Opprett og lagre samtykke
        $samtykke = new Samtykke\Person( $person, $innslag );
        $samtykke->setStatus( $status, $ip );
        $samtykke->persist();

        // Hvis vi har foresatt, lagre dette også
        if( $user->getForesattMobil() != null ) {
            $samtykke->setForesatt(
                $user->getForesattNavn(),
                $user->getForesattMobil()
            );
            $samtykke->persist();
        }
        return true;
    }
}
?>