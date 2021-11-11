<?php
namespace UKMNorge\DeltaBundle\Services;

use Exception;

use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Innslag\Typer\Typer;


class InnslagFunctions {

    public function __construct($container) {
        $this->container = $container;
    }
    
	/**
     * Oppretter innslaget, og legger til kontaktperson hvis dette skal gjøres
     * _@route: <ukmid/pamelding/$k_id-$pl_id/$type/opprett/>
     * 
     * Videresender til rediger innslag etter oppretting
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     */
    public function createInnslag(Int $k_id, Int $pl_id, String $type, $_this, $isApi = false)
    {
        $route_data = [
            'k_id' => $k_id,
            'pl_id' => $pl_id,
            'type' => $type,
        ];

        // Setup input data
        $type = Typer::getByKey($type);
        $user = $_this->hentCurrentUser();
        $innslagService = $_this->get('ukm_api.innslag');
        $personService = $_this->get('ukm_api.person');

        // Hent arrangement og sjekk at det er mulig å melde på innslag
        $arrangement = new Arrangement($pl_id);
        if (!$arrangement->erPameldingApen($type->getFrist())) {
            throw new Exception('Påmeldingsfristen er ute!');
        }

        $kommune = new Kommune($k_id);

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Opprett person
            $person = $personService->opprett(
                $user->getFirstname(),
                $user->getLastname(),
                $user->getPhone(),
                $kommune,
                $arrangement
            );
            // Sett alder og e-post basert på user-bundle-alder
            $person->setFodselsdato($user->getBirthdate());
            $person->setEpost($user->getEmail());

            // Oppdater verdier i UserBundle
            $user->setPameldUser($person->getId());
            $_this->container->get('fos_user.user_manager')->updateUser($user);

            // Se om brukeren har fått tildelt en Wordpress-innloggingsbruker (via UKMusers etc), og prøv å koble den.
            $personService = $_this->container->get('ukm_api.person');
            $personService->addDeltaIDToWordpressLoginUser($person->getId(), $user->getId());

            $lagrePerson = true;
        }
        // Hvis brukeren er registrert i systemet fra før
        else {
            $person = $personService->hent($user->getPameldUser());
            $lagrePerson = false;
        }

        $innslag = false;
        // Hvis brukeren (kontaktpersonen) allerede er påmeldt på denne mønstringen
        // i denne _tittelløse_ kategorien, gå til redigering
        if ($type->erEnkeltPerson()) {
            try {
                $innslag = $innslagService->hentEnkeltPersonInnslag($type, $arrangement);
            } catch (Exception $e) {
                // Hvis personen ikke er påmeldt fra før, opprett en ved å fortsette.
                // Ignorerer derfor Exception $e
            }
        }

        // Opprett nytt innslag hvis vi ikke nettopp fant det
        if (!$innslag) {
            $innslag = $innslagService->opprett(
                $kommune,
                $arrangement,
                $type,
                $person
            );
        }

        // Lagre endringer på personobjektet
        if ($lagrePerson) {
            $personService->lagre($person, $innslag->getId());
        }

        // Flytt personvern-tilbakemelding (nå lagret på delta user-objektet) over på person-objektet
        $personService->oppdaterPersonvern($innslag);

        $route_data['b_id'] = $innslag->getId();

        // Enkeltpersoner kan potensielt være ferdig påmeldt nå.
        // Ved å trigge lagre, trigges også evalueringen av mangler.
        if ($type->erEnkeltPerson()) {
            $innslagService->lagre($innslag);
        }

        // IT IS API
        if($isApi) {
            return $innslag;
        }

        return $route_data;
    }


}

?>