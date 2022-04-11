<?php
namespace UKMNorge\DeltaBundle\Services;

use Exception;
use DateTime;

use Symfony\Component\HttpFoundation\Request;


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
        if (!$arrangement->erPameldingApen($type->getFrist(1)) || !$arrangement->erPameldingApen($type->getFrist(2))) {
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


    public function saveNewPerson(Int $k_id, Int $pl_id, String $type, Int $b_id, String $fornavn, String $etternavn, Int $alder, Int $mobil, String $rolle, $_this, $logger) {
        $request = Request::createFromGlobals();
        $innslagService = $_this->get('ukm_api.innslag');
        $personService = $_this->get('ukm_api.person');

        try {

            $innslag = $innslagService->hent($b_id);
            $arrangement = new Arrangement($pl_id);
            $kommune = new Kommune($k_id);

            try {
                // Opprett personen
                $person = $personService->opprett(
                    $fornavn,
                    $etternavn,
                    $mobil,
                    $kommune,
                    $arrangement
                );
            } catch (Exception $e) {
                // Får vi ikke til å opprette personen, returnerer vi en exception og sender en error til loggen
                $logger->error(
                    "UKMDeltaBundle:Innslag:saveNewPerson - Klarte ikke å opprette person på innslag " . $b_id . ". Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage() . ".\n\nData: ",
                    [$request->request->get('fornavn'), $request->request->get('etternavn'), $mobil, $kommune, $arrangement]
                );
                
                throw new Exception("danger", "Klarte ikke å lagre " . $request->request->get('fornavn'));
            }

            // Legg til i innslaget, sett rolle
            $person->setRolle($rolle);

            // Sett alder
            $person->setFodselsdato(new DateTime(((int) date('Y') - $request->request->get('alder')) . '-01-01'));

            $innslagService->leggTilPerson($innslag, $person);

        } catch (Exception $e) {
            $logger->error("Klarte ikke å legge til " . $person->getNavn() . " i innslag " . $innslag->getNavn() . ". Feil: " . $e->getMessage());
            throw new Exception("danger", "Klarte ikke å legge til " . $person->getNavn() . " i innslaget! Feilkode: " . $e->getCode());
        }

        return array(
            'p_id' => $person->getId(),
            'fornavn' => $fornavn,
            'etternavn' => $etternavn,
            'alder' => $alder,
            'mobil' => $mobil,
            'rolle' => $rolle,
        );
    }
    


}

?>