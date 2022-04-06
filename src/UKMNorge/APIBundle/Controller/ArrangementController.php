<?php

namespace UKMNorge\APIBundle\Controller;

use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use UKMNorge\Nettverk\Omrade;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Innslag\Venteliste\Venteliste;


require_once('UKM/Autoloader.php');

class ArrangementController extends SuperController {

    /**
     * Hent arrangement
     *
     * @param Int $arrangementId
     * @return JsonResponse
     */
    public function getArrangementAction(Int $arrangementId) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');
        
        try{
            $arrangement = $innslagService->hentArrangement($arrangementId);
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => $e->getMessage()]);
            return $response;
        }

        $response->setData($arrangement);
        return $response;
    }
    
    /**
     * Hent arrangement
     *
     * @param Int $arrangementId
     * @return JsonResponse
     */
    public function getArrangementIKommuneAction(Int $k_id) {
        $response = new JsonResponse();
        $omrade = Omrade::getByKommune($k_id);
        $innslagService = $this->get('ukm_api.innslag');

        
        $arrangementer = $omrade->getKommendeArrangementer()->getAll();
        $arrangementer_arr = [];
        // Hvis det er fellesmønstring, legg til kommuner
        foreach($arrangementer as $arrangement) {
            if($arrangement->erFellesmonstring()) {
                $kommuner = $arrangement->getKommuner()->getAll();
                foreach($kommuner as $kommune) {
                    $arrangement->kommuner_fellesmonstring[$kommune->getId()] = array(
                        'id' => $kommune->getId(),
                        'navn' => $kommune->getNavn()
                    );
                }
            }
            else {
                $arrangement->kommuner_fellesmonstring = null;
            }

            if(!$innslagService->ledigPlassPaaArrangement($arrangement)) {
                $arrangement = (array) $arrangement;
                $arrangement['ventelisteLedigPlass'] = false;
            }
            else {
                $arrangement = (array) $arrangement;
                $arrangement['ventelisteLedigPlass'] = null;
            }

            $arrangementer_arr[] = $arrangement;
        }

        try{
            $response->setData($arrangementer_arr);
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => $e->getMessage()]);
            return $response;
        }

        return $response;
    }

    /**
     * Legg til bruker i venteliste
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function ventelisteAction(Request $request) {
        $response = new JsonResponse();
        $personService = $this->get('ukm_api.person');
        $innslagService = $this->get('ukm_api.innslag');

        try{
            $data_arr = $this->getData($request, ['k_id', 'pl_id']);
            $k_id = $data_arr['k_id'];
            $pl_id = $data_arr['pl_id'];
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        $arrangement = $innslagService->hentArrangement($pl_id);
        $venteliste = $arrangement->getVenteliste();

        // Hvis det er ledig plass på arrangementet så stopp prosessen.
        // Eksempel: Brukeren trykker knappen 'set meg i venteliste', mens en annen bruker er meld av og da blir ledig plass og trenger ikke brukeren å være i venteliste
        if($arrangement->getAntallPersoner() < $arrangement->getMaksAntallDeltagere()) {            
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => 'Oops! noe gikk feil! Prøv igjen']);
            return $response;
        }

        $kommune = new Kommune($k_id);

        $user = $this->hentCurrentUser();

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
            $this->container->get('fos_user.user_manager')->updateUser($user);

            // Se om brukeren har fått tildelt en Wordpress-innloggingsbruker (via UKMusers etc), og prøv å koble den.
            $personService = $this->container->get('ukm_api.person');
            $personService->addDeltaIDToWordpressLoginUser($person->getId(), $user->getId());
        }
        // Hvis brukeren er registrert i systemet fra før
        else {
            $person = $personService->hent($user->getPameldUser());
        }

        try {
            $venteliste->addPerson($person, $kommune);
        } catch(Exception $e) {
            $this->get('logger')->error("UKMDeltaBundle:Innslag:venteliste - Feil oppsto i forbindelse med lagring av person i venteliste! Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage());
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => 'Oops! Klarte ikke å lagre endringene.', 'exception' => $e->getMessage()]);
            return $response;
        }

        $response->setData(true);
        return $response;
    }

    /**
     * Fjern bruker fra venteliste
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromVentelistesAction(Request $request) {
        $response = new JsonResponse();
        $user = $this->hentCurrentUser();
        $innslagService = $this->get('ukm_api.innslag');
        
        // Hent data
        try{
            $data_arr = $this->getData($request, ['pl_id']);
            $pl_id = $data_arr['pl_id'];
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }
        
        $vePerson = Venteliste::staarIVenteliste($user->getPameldUser(), $pl_id);

        if($vePerson) {
            try{
                $arrangement = $innslagService->hentArrangement($pl_id);
                $venteliste = $arrangement->getVenteliste();
                $venteliste->removePerson($vePerson);
            } catch(Exception $e) {
                $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                $response->setData(['errorMessage' => $e->getMessage()]);
                return $response;
            }
        }

        $response->setData(['result' => true]);
        return $response;
    }

    /**
     * Hent alle personer som venter i venteliste fra venteliste
     * 
     * @return JsonResponse
     */
    public function getArrangementerVentelisteAction() {
        $response = new JsonResponse();
        $user = $this->hentCurrentUser();
        
        try{
            $arrangementer = Venteliste::getArrangementerByPersonId($user->getPameldUser());
            $response->setData($arrangementer);
            return $response;
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => 'Oops! noe gikk feil! Prøv igjen']);
        }
    }
}