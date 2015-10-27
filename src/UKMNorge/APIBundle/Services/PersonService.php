<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use person;
use DateTime;
use Exception;

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
		echo '<br>opprett():<br/>';
		var_dump($fornavn);
        var_dump($etternavn);
        var_dump($mobil);

		$user = $this->container->get('ukm_user')->getCurrentUser();

		// Oppretter et tomt personobjekt (Se person.class.php)
		$person = new person(false, false);

		// Sjekk om personen finnes
		$finnes = $person->getExistingPerson($fornavn, $etternavn, $mobil);

		var_dump($finnes);
		if ($finnes)
			return $finnes;

		$person->create();
		// Oppdater verdier i person-objektet
		$person->set('p_firstname', $fornavn);
		$person->set('p_lastname', $etternavn);
		$person->set('p_phone', $mobil);
		// Send data til databasen
		var_dump($person);
		$person->lagre('delta', $user->getId(), $pl_id);

		return $person;
	}

	public function adresse($person, $adresse, $postnummer, $poststed) {
		if (!get_class($person) == 'person') {
			throw new Exception ('Kunne ikke oppdatere adresse - feil objekt mottatt. Ventet person, fikk ' . get_class($person));
		}

		$person->set('p_adress', $adresse);
		$person->set('p_postnumber', $postnummer);
		$person->set('p_postplace', $poststed);
		$person->lagre();
	}

	public function hent($id) {
		$person = new person($id, false);

		if (!is_numeric($person->get('p_id'))) {
			throw new Exception('Fant ikke person med id ' . $id);
		}

		return $person;
	}

	public function leggTilInnslag($personID, $b_id) {
		// $person = $this->hent($personID);

		// var_dump($person);
		// $person->relate($b_id);
	}

	public function alder($person) {

		$birthdate = new DateTime();
		$birthdate->setTimestamp($person->get('p_dob'));

        $now = new DateTime('now');
        $age = $birthdate->diff($now)->y;
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

		if ($person->getAge() != $alder) {
			// Konverter fra alder i tall til år
			$dob = new DateTime();
			$birthyear = date("Y") - $alder;
			//var_dump($birthyear);
			$dob->setDate($birthyear, 1, 1); // Setter året til fødselsåret
			//var_dump($dob);
			$dob = $dob->getTimestamp();

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
}

?>