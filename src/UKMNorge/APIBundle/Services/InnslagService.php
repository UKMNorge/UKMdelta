<?php

namespace UKMNorge\APIBundle\Services;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Database\SQL\Update;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Context\Context;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Innslag\Samling;
use UKMNorge\Innslag\Titler\Tittel;
use UKMNorge\Innslag\Typer\Type;
use UKMNorge\Innslag\Typer\Typer;
use UKMNorge\Log\Logger;
use UKMNorge\Innslag\Write as WriteInnslag;
use UKMNorge\Innslag\Titler\Write as WriteTittel;
use UKMNorge\Innslag\Personer\Write as WritePerson;

require_once('UKM/Autoloader.php');

class InnslagService
{

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Opprett et nytt innslag
     *
     * @param Kommune $kommune
     * @param Arrangement $arrangement
     * @param Type $type
     * @param Person $kontakt
     * @return void
     */
    public function opprett(Kommune $kommune, Arrangement $arrangement, Type $type, Person $kontakt)
    {
        $this->_setupLogger($arrangement->getId());
        // Opprett innslag
        $innslag = WriteInnslag::create(
            $kommune,
            $arrangement,
            $type,
            $type->erEnkeltPerson() ? $kontakt->getNavn() : 'Innslag uten navn',
            $kontakt
        );

        // Legg til kontaktpersonen i innslaget
        $innslag->getPersoner()->leggTil($kontakt);
        WriteInnslag::savePersoner($innslag);

        $setValidated = new Update(
            'smartukm_band',
            [
                'b_id' => $innslag->getId()
            ]
        );
        $setValidated->add('b_validatedby', $kontakt->getMobil());
        $setValidated->add('b_password', 'delta_' . $this->container->get('ukm_user')->getCurrentUser()->getId());
        $setValidated->run();

        return $innslag;
    }

    /**
     * Lagre endringer i et innslag-objekt
     *
     * @param Innslag $innslag
     * @return void
     * @throws Exception
     */
    public function lagre(Innslag $innslag)
    {
        $this->_setupLogger($innslag->getHomeId());
        $this->sjekkTilgang($innslag);
        
        // Lagre bare hvis arrangement ikke har antall begrensning eller det er ledig plass
        $arrangement = $this->hentArrangement($innslag->context->monstring->id);
        if($arrangement->erMaksAntallAktivert()) {
            if($arrangement->getMaksAntallDeltagere() <= $arrangement->getAntallPersoner()) {
                throw new Exception('Det er ikke ledig plass på: ' . $arrangement->getNavn());
            }
        }
    
        return WriteInnslag::save($innslag);;
    }

    /**
     * Opprett en blank tittel for innslaget
     *
     * @param Innslag $innslag
     * @return Tittel
     */
    public function opprettTittel(Innslag $innslag)
    {
        $this->_setupLogger($innslag->getHomeId());

        $tittel = WriteTittel::create($innslag);
        $tittel->setContext(
            Context::createInnslag(
                $innslag->getId(),
                $innslag->getType()->getKey(),
                $innslag->getHome()->getId(),
                $innslag->getHome()->getType(),
                $innslag->getHome()->getSesong(),
                $innslag->getHome()->getFylke()->getId(),
                $innslag->getHome()->getKommuner()->getIdArray()
            )
        );

        return $tittel;
    }

    /**
     * Slett en tittel (fra innslaget)
     *
     * @param Innslag $innslag
     * @param Tittel $tittel
     * @throws Exception hvis feilet (fra WriteTittel)
     */
    public function fjernTittel(Innslag $innslag, Tittel $tittel)
    {
        $this->_setupLogger($innslag->getHomeId());

        WriteTittel::fjern($tittel);
    }

    /**
     * Lagre alle titler i innslaget
     *
     * @param Innslag $innslag
     * @return void
     */
    public function lagreTitler(Innslag $innslag, Tittel $tittel)
    {
        $this->_setupLogger($innslag->getHomeId());
        return WriteTittel::save($tittel);
    }

    /**
     * Hent et gitt innslag
     *
     * @param Int $innslagID
     * @return Innslag $innslag
     * @throws Exception Ikke funnet / ikke tilgang
     */
    public function hent(Int $innslagID)
    {
        $innslag = Innslag::getById($innslagID, true); // True: hent også uferdige innslag
        $this->sjekkTilgang($innslag);
        return $innslag;
    }

    /**
     * Hent current user
     *
     * @return $current_user
     */
    public function hentCurrentUser()
    {
        return $this->container->get('ukm_user')->getCurrentUser();
    }

    /**
     * Hent arrangement
     *
     * @param Int $arrangementID
     * @return Arrangement
     */
    public function hentArrangement(Int $arrangementID)
    {
        return new Arrangement($arrangementID);
    }

    /**
     * Meld av et innslag
     *
     * @param Int $innslagID
     * @param Int $arrangementID
     * @return Bool true
     * @throws Exception ikke tilgang, feil osv
     */
    public function meldAv(Int $innslagID, Int $arrangementID)
    {
        $this->_setupLogger($arrangementID);
        $innslag = $this->hent($innslagID);
        $arrangement = $this->hentArrangement($innslag->context->monstring->id);

        // Sjekk at mønstringen tillater av- og påmeldinger
        $this->sjekkFrist($innslag);

        WriteInnslag::meldAv($innslag);

        // Hvis arrangement har venteliste og denne brukeren er meld av
        if($arrangement->erMaksAntallAktivert()) {
            $arrangement->getVenteliste()->updatePersoner();
        }
        
        return true;
    }

    /**
     * Hent eksisterende tittelløs påmelding hvis den eksistere påmeldt gitt arrangement
     * Man kan kun ha én påmelding per tittelløs-kategori (media, konferansier, arrangør)
     * per arrangement. Dette forhindrer duplikat hvis deltakeren prøver å melde seg på
     * på nytt.
     *
     * @param Type $type
     * @param Arrangement $arrangement
     * @return Innslag
     * @throws Exception found none
     */
    public function hentEnkeltPersonInnslag(Type $type, Arrangement $arrangement)
    {
        $alle_innslag = $this->hentInnslagFraKontaktperson();
        foreach ($alle_innslag->getAll() as $innslag) {
            if ($innslag->getType()->getKey() == $type->getKey() && $innslag->getHome()->getId() == $arrangement->getId()) {
                return $this->hent($innslag->getId()); // Bruker hent for å sjekke rettigheter
            }
        }
        throw new Exception('Har ingen innslag av typen ' . $type->getNavn() . ' påmeldt til ' . $arrangement->getNavn());
    }

    /**
     * Hent alle innslag denne kontaktpersonen har
     *
     * @return Samling
     */
    public function hentInnslagFraKontaktperson()
    {
        $user = $this->hentCurrentUser();

        if ($user->getPameldUser() != null) {
            $person = $this->container->get('ukm_api.person')->hent($user->getPameldUser());
            $p_id = $person->getId();
        } else {
            $p_id = 0;
        }
        
        $context = Context::createKontaktperson($p_id);
        $alle_innslag = new Samling($context);

        try {
            foreach( $alle_innslag->getAll() as $innslag ) {
                try {
                    $innslag->getHome();
                } catch( Exception $e ) {
                    // Workaround for noen få brukere som har slettede innslag.
                    $this->container->get('logger')->notice("UKMID:index - Hopper over et påmeldt innslag på grunn av slettet arrangement! Dette er en bug som ikke skal oppstå etter sesongen 2020. Feilmelding: ".$e->getCode(). ", ".$e->getMessage()."\r\n\tInnslag-id: ".$innslag->getId());
                    $alle_innslag->fjern( $innslag );
                }
            }        
        } catch( Exception $e ) {
            // Ukjent innslag-type (vi har opplevd dette på en del innslag fra tidligere år)
            if($e->getCode() == 110002) {
                $this->container->get('logger')->notice("UKMID:index - Hopper over innslag på grunn av manglende `b_kategori` når `bt_id` = 1. Feilmelding: ".$e->getCode(). ", ".$e->getMessage());
                // do nothing
            } else {
                throw $e;
            }
        }
        return $alle_innslag;
    }

    /**
     * Legg til en person i innslaget
     * 
     * @throws Exception hvis manglende rettighet til innslaget, fristen er ute, logger feil satt opp, lagring feilet.
     * @param Int $innslagId
     * @param Person $person
     * @return void
     */
    public function leggTilPerson(Innslag $innslag, Person $person)
    {
        // Sjekk at mønstringen tillater av- og påmeldinger
        $this->sjekkFrist($innslag);
        // Sjekk at vi kan redigere innslaget
        $this->sjekkTilgang($innslag);
        // Setup logger
        $this->_setupLogger($innslag->getHomeId());

        $innslag->getPersoner()->leggTil($person);

        WriteInnslag::savePersoner($innslag);
        WritePerson::save($person);
        return true;
    }

    /**
     * Fjern en person fra et innslag
     *
     * @param Int $innslagID
     * @param Int $arrangementID
     * @return Bool true
     * @throws Exception ikke tilgang, feil osv
     */
    public function fjernPerson(Int $innslagID, Int $personID)
    {
        $innslag = $this->hent($innslagID);

        // Sjekk at mønstringen tillater av- og påmeldinger
        $this->sjekkFrist($innslag);

        // Fjern personen
        $person = $innslag->getPersoner()->get($personID);
        $innslag->getPersoner()->fjern($person);

        // Lagre
        $this->_setupLogger($innslag->getHomeId());
        WriteInnslag::savePersoner($innslag);
        return true;
    }

    /**
     * Sjekk at alt er ok (Frist og tilgang)
     *
     * @param Innnslag $innslag
     * @param [type] $type
     * @return void
     */
    public function sjekk(Innslag $innslag)
    {
        $this->sjekkTilgang($innslag);
        if (!$this->sjekkFrist($innslag)) {
            throw new Exception('Påmeldingsfristen er ute!');
        }
    }

    /**
     * Er påmelding åpen for dette innslaget?
     *
     * @param Innslag $innslag
     * @param Arrangement $arrangement
     * @return void
     */
    public function sjekkFrist(Innslag $innslag)
    {
        return $innslag->getHome()->erPameldingApen($innslag->getType()->getFrist());
    }

    /**
     * Sjekk at current user har tilgang til innslaget
     *
     * @param Innslag $innslag
     * @return Bool
     * @throws Exception ikke tilgang
     **/
    public function sjekkTilgang(Innslag $innslag)
    {
        $user = $this->hentCurrentUser();
        if (($innslag->getEier() != 'delta_' . $user->getId()) && ($innslag->getKontaktperson()->getId() != $user->getPameldUser())) {
            throw new Exception('Du har ikke tilgang til dette innslaget!');
        }
    }

    /**
     * Sjekk om innslagets type stemmer med type fra URL(-ish)
     * Brukes for å forhindre trøbbel i databasen, hvis brukeren for moro
     * skyld endrer URL'en.
     *
     * @param Innslag $innslag
     * @param Type $type
     * 
     * @echo RedirectResponse if wrong type
     * @throws Exception if wrong type
     * @return void
     */
    public function sjekkBandtype(Innslag $innslag, Type $type)
    {
        // Redirect til rett type om vi kan?
        if ($innslag->getType()->getKey() != $type->getKey()) {
            $path = $this->container->get('router')->generate(
                $this->container->get('request')->get('_route'),
                [
                    'k_id' => $this->container->get('request')->get('k_id'),
                    'pl_id' => $this->container->get('request')->get('pl_id'),
                    'type' => $type->getKey(), // Sett korrekt type for innslaget
                    'b_id' => $innslag->getId()
                ],
                301
            );
            echo new RedirectResponse($path);

            // Stop execution somehow.
            throw new Exception('Feil kategori for innslaget! Vi videresender deg nå.');
        }
    }

    /**
     * Setup UKM Logger
     *
     * @param Int $arrangement_id
     * @return void
     */
    private function _setupLogger(Int $arrangement_id)
    {
        Logger::setID('delta', $this->hentCurrentUser()->getId(), $arrangement_id);
    }

}
