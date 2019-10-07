<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use DateTime;
use Exception;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Write;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Logger\Logger;
use UKMNorge\Samtykke\Person as PersonSamtykke;

require_once('UKM/Autoloader.php');

class PersonService {
	public function __construct($container) {
		$this->container = $container;
    }
    
    /**
     * Setup UKM Logger
     *
     * @param Int $arrangement_id
     * @return void
     */
    private function _setupLogger( Int $arrangement_id ) {
        Logger::setID('delta', $this->hentCurrentUser()->getId(), $arrangement_id);
    }

    /**
     * Hent en gitt person.
     * Relatert til innslaget hvis parameter 2 (innslagID) er angitt
     *
     * @param Int $personID
     * @param Int $innslagID
     * @return Person
     * @throws Exception Person not found
     */
	public function hent(Int $personID, Int $innslagID=null) {
        if( $innslagID !== null ) {
            $innslag = $this->hentInnslag( $innslagID );
            $person = $innslag->getPersoner()->get( $personID );
        } else {
            $person = Person::loadFromId( $personID );
        }
        
        return $person;
    }
    
    /**
     * Hent innslag fra Innslag-service
     *
     * @param Int $innslagID
     * @return Innslag
     * @throws Exception har ikke tilgang
     */
    public function hentInnslag( Int $innslagID ) {
        return $this->container->get('ukm_api.innslag')->hent( $innslagID );
    }

    /**
     * Hent aktiv bruker
     *
     * @return 
     */
    public function hentCurrentUser() {
        return $this->container->get('ukm_user')->getCurrentUser();
    }


    /**
     * Opprett et person-objekt.
     * Returnerer eksisterende person hvis den allerede finnes
     *
     * @param String $fornavn
     * @param String $etternavn
     * @param Int $mobil
     * @param Kommune $kommune
     * @return Person $person
     */
	public function opprett( String $fornavn, String $etternavn, Int $mobil, Kommune $kommune, Arrangement $arrangement ) {
        $this->_setupLogger( $arrangement->getId() );

        $person = Write::create(
            $fornavn,
            $etternavn,
            $mobil,
            $kommune
        );
        
		return $person;
	}

    /**
     * Lagre en persons fornavn
     *
     * @param Int $personID
     * @param String $fornavn
     * @return void
     */
	public function lagre( Person $person, Int $innslagID ) {
        $this->_setupLogger( $this->hentInnslag( $innslagID )->getHomeId() );
        $this->sjekkTilgang( $person, $innslagID );
        Write::saveRolle( $person );
        return Write::save( $person );
    }

    /**
     * Sjekk om brukeren har tilgang til å redigere personen
     *
     * @param Person $person
     * @param Int $innslagID
     * @return Innslag $innslag
     */
    public function sjekkTilgang( Person $person, Int $innslagID ) {
        $innslag = $this->container->get('ukm_api.innslag')->hent( $innslagID );
        if( !$innslag->getPersoner()->har( $person ) ) {
            throw new Exception('Beklager, du har ikke tilgang til å endre denne personen');
        }
        return $innslag;
    }

    /**
     * Oppdater personvern-valg for brukeren
     * Setter samme innstilling på ss3_participant-objektet som delta-brukeren
     */
    public function oppdaterPersonvern( Innslag $innslag ) {
        $user = $this->hentCurrentUser();
        $person = $this->hent( $user->getPameldUser() );
        
        $status = $user->getSamtykke() ? 
            'godkjent' : 
            'ikke_godkjent';

        $ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? 
            $_SERVER['HTTP_CF_CONNECTING_IP'] :
            $_SERVER['REMOTE_ADDR'];

        // Opprett og lagre samtykke
        $samtykke = new PersonSamtykke( $person, $innslag );
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