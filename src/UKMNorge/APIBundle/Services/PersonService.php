<?php
namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use DateTime;
use Exception;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Database\SQL\Update;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Write;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Log\Logger;
use UKMNorge\Samtykke\Person as PersonSamtykke;
use UKMNorge\UserBundle\Entity\User;
use UKMNorge\Wordpress\LoginToken;

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
     * Returnerer eksisterende person hvis den allerede finnes.
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

    public static function hentWordpressUserId(User $user) {
        $sql = new Query("SELECT `wp_id` FROM `ukm_delta_wp_user` WHERE `delta_id` = '#delta_id'", ['delta_id' => $user->getId()]);
        $sql->run();
        $wp_id = $sql->getField();
    
        # Hvis vi ikke finner $wp_id, prøv å sett delta-id basert på p_id
        if( null == $wp_id ) {
            self::addDeltaIDToWordpressLoginUser( $user->getPameldUser(), $user->getId());
            $sql->run();
            $wp_id = $sql->getField();
        }

        if( null == $wp_id ) {
            throw new Exception("Er du sikker på at du har fått lov til å logge på arrangørsystemet?");
        }
        return $wp_id;
    }

    /**
     * Oppdater delta/wp-innloggingsbruker.
     * Skal brukes hver gang man kaller setPameldUser på en Delta-bruker, som vil si når vi kobler en Delta-bruker mot en påmelding.
     * Dersom delta-brukeren ikke finnes i den tabellen (det vil si at det ikke er opprettet WP-innlogging), gjør vi ingenting.
     * 
     * @param Int delta_user_id - Bruker-ID fra Delta's User
     * @param Int p_id - Participant ID.
     * @return Int - affected rows. 0 ved feil, 1 ved OK.
     */
    public static function updateWordpressLoginUserParticipantIdForDeltaId(Int $delta_user_id, Int $p_id) {
        $sql = new Update('ukm_delta_wp_user', ['delta_id' => $delta_user_id]);
        $sql->add("participant_id", $p_id);
        return $sql->run();
    }

    /**
     * Legg til delta-id i Delta/WP-innloggingstabell
     * Kalles når det er forsøkt en innlogging til WP der delta_user ikke finnes i delta_wp_user-tabellen, men p_id matcher.
     *
     * @param Int p_id - Participant ID.
     * @param Int delta_user_id - Delta's bruker-ID
     * @return Int - affected rows. 0 ved feil, 1 ved OK.
     */
    public static function addDeltaIDToWordpressLoginUser(Int $p_id, Int $delta_user_id) {
        $sql = new Update('ukm_delta_wp_user', ['participant_id' => $p_id]);
        $sql->add("delta_id", $delta_user_id);
        return $sql->run();
    }

    public static function hentWordpressLoginURL(LoginToken $token) {
        return "https://".UKM_HOSTNAME."/autologin/?wp_id=".$token->wp_id."&token_id=".$token->token_id."&token=".$token->secret;
    }
}
?>