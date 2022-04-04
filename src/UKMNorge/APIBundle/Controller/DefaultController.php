<?php

namespace UKMNorge\APIBundle\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use UKMNorge\Database\SQL\Query;
use UKMNorge\APIBundle\Services\ArrangementService;
use UKMNorge\APIBundle\Services\InnslagService;
use UKMNorge\Geografi\Kommune;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Innslag\Typer\Typer;
use UKMNorge\Geografi\Fylker;
use UKMNorge\Nettverk\Omrade;



use UKMNorge\DeltaBundle\Controller\InnslagController as InnslagController;
use UKMNorge\Geografi\Fylke;

require_once('UKM/Autoloader.php');

class DefaultController extends SuperController {

    public function indexAction($name)
    {
        return $this->render('UKMAPIBundle:Default:index.html.twig', array('name' => $name));
    }

    public function poststedAction($postnummer) {
        $response = new JsonResponse();


		$qry = new Query("SELECT `postalplace` FROM `smartukm_postalplace` WHERE `postalcode` = #code", array("code" => $postnummer));
		$place = $qry->run('field', 'postalplace');
		if(empty($place)) {
            $response->setData(array('sted' => false)); 
        }
        else {
            $response->setData(array('sted' => $place));
        }

    	return $response;
    }


    /* ---------------------------- Fylker og kommuner ---------------------------- */
    
    /**
     * Hent alle fylker og kommuner
     * @return JsonResponse
     */
    public function getAlleFylkerOgKommunerAction() {
        $response = new JsonResponse();

        $fylker = [];

        foreach(Fylker::getAll() as $fylke) {
            $fylker[] = $fylke;

            $kommuner_arr = [];
            foreach($fylke->getKommuner()->getAll() as $kommune) {

                $kommuner_arr[] = [
                    "id" => $kommune->getId(),
                    "navn" => $kommune->getNavn(),
                    'erAktiv' => $kommune->erAktiv(),
                    'action' => $kommune->getAttr('action'),
                    'link' => $kommune->getLink(),
                    'arrangementer_loaded' => false,
                    'arrangementer' => []
                ];
            }

            $fylke->kommuner = $kommuner_arr;
            
        }


        try{
            return $response->setData($fylker);
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }
    }
    
    /* ---------------------------- Fylker ---------------------------- */

    /**
     * Hent et fylke med id
     * @param string $fylke_id
     * @return JsonResponse
     */
    public function getFylkeAction($fylke_id) {
        $response = new JsonResponse();
        
        try{
            return $response->setData(Fylker::getById($fylke_id));
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }
    }

    /**
     * Hent alle fylker
     * @return JsonResponse
     */
    public function getAlleFylkerAction() {
        $response = new JsonResponse();
        
        try{
            return $response->setData(Fylker::getAll());
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }
    }

    /* ---------------------------- Kommune ---------------------------- */
    
    /**
     * Hent alle kommuner i et fylke ved å gi fylke id
     * @param string $fylke_id
     * @return JsonResponse
     */
    public function getAlleKommunerIFylkeAction($fylke_id) {
        $response = new JsonResponse();
        
        try{
            $fylke = Fylker::getById($fylke_id);
            $kommuner = $fylke->getKommuner()->getAll();
            
            $kommuner_arr = [];
            foreach($kommuner as $kommune) {
                $kommuner_arr[] = [
                    "id" => $kommune->getId(),
                    "navn" => $kommune->getNavn(),
                    'erAktiv' => $kommune->erAktiv(),
                    'action' => $kommune->getAttr('action'),
                    'link' => $kommune->getAttr('link'),
                ];
            }

            return $response->setData($kommuner_arr);
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }
    }


    /* ---------------------------- Person ---------------------------- */
    
    /**
     * Hent alle personer i ett innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllPersonsAction($b_id) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');
        
        try{
            $innslag = $innslagService->hent($b_id);

            $personer = $innslag->getPersoner()->getAll();

            $response->setData($personer);

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;

    }


    /**
     * Opprett ny person
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createNewPersonAction(Request $request) {
        $response = new JsonResponse();

        // Hent data
        try{
            $data_arr = $this->getData($request, ['k_id', 'pl_id', 'type', 'b_id', 'fornavn', 'etternavn', 'alder', 'mobil', 'rolle']);
            $k_id = $data_arr['k_id'];
            $pl_id = $data_arr['pl_id'];
            $type = $data_arr['type'];
            $b_id = $data_arr['b_id'];
            $fornavn = $data_arr['fornavn'];
            $etternavn = $data_arr['etternavn'];
            $alder = $data_arr['alder'];
            $mobil = $data_arr['mobil'];
            $rolle = $data_arr['rolle'];

        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        $innslagFunctions = $this->get('ukm_delta.innslagfunctions');
        
        try{
            $response->setData($innslagFunctions->saveNewPerson($k_id, $pl_id, $type, $b_id, $fornavn, $etternavn, $alder, $mobil, $rolle, $this, $this->get('logger')));
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

    
    /**
     * Rediger person
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function editPersonAction(Request $request) {
        $response = new JsonResponse();

        // Hent data
        try{
            $data_arr = $this->getData($request, ['b_id', 'p_id', 'fornavn', 'etternavn', 'alder', 'mobil', 'rolle']);
            $b_id = $data_arr['b_id'];
            $p_id = $data_arr['p_id'];
            $fornavn = $data_arr['fornavn'];
            $etternavn = $data_arr['etternavn'];
            $alder = $data_arr['alder'];
            $mobil = $data_arr['mobil'];
            $rolle = $data_arr['rolle'];

            $response->setData(
                $this->get('ukm_api.innslag')->editPerson($b_id, $p_id, $fornavn, $etternavn, $alder, $mobil, $rolle)
            );

        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

    /**
     * Fjern en person fra innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function removePersonAction(Request $request) {
        $response = new JsonResponse();

        // Hent data
        try{
            $data_arr = $this->getData($request, ['k_id', 'pl_id', 'type', 'b_id', 'p_id']);
            
            $this->get('logger')->notice("DeltaBundle:removePerson - Remove person request received for person " . $data_arr['p_id'] . " from band " . $data_arr['b_id'] . ".");

            try {
                $this->get('ukm_api.innslag')->fjernPerson($data_arr['b_id'], $data_arr['p_id']);
                $response->setData(array('p_id' => $data_arr['p_id']));
            } catch (Exception $e) {
                $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                $response->setData($e->getMessage());
                return $response;
            }

        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }


    /* ---------------------------- Tittel ---------------------------- */
    
    /**
     * Hent alle personer i ett innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllTitlerAction($b_id) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');
        
        try{
            $innslag = $innslagService->hent($b_id);
            
            $typeKey = $innslag->getType()->getKey();
            // Check if Innslag Type is available for Tittel
            if($typeKey == 'cosplay' || $typeKey == 'dataspillgruppe') {
                $response->setData(null);
                return $response;
            }
          

            $response->setData($innslag->getTitler()->getAll());

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

    public function createOrEditTittelAction(Request $request) {
        $response = new JsonResponse();

        try {
            $data_arr = $this->getData($request, ['b_id', 't_id', 'tittel'], ['lengde', 'selvlaget', 'melodiforfatter', 'sangtype', 'tekstforfatter', 'koreografi', 'leseopp', 'type']);
            
            $b_id = $data_arr['b_id']; // innslag id
            $t_id = $data_arr['t_id']; // tittel id (if 'new' - create Tittel)
            $tittelTekst = $data_arr['tittel']; // tittel string
            $lengde = $data_arr['lengde']; // varighet           
            $selvlaget = $data_arr['selvlaget'];
            $melodiforfatter = $data_arr['melodiforfatter'];
            $sangtype = $data_arr['sangtype'];
            $tekstforfatter = $data_arr['tekstforfatter'];
            $koreografi = $data_arr['koreografi'];
            $leseopp = $data_arr['leseopp']; // 0 or 1 (false, true)
            $type = $data_arr['type'];

            $request = Request::createFromGlobals();
            $innslagService = $this->get('ukm_api.innslag');
            $innslag = $innslagService->hent($b_id);

            // Opprett tittel
            if ($t_id == 'new') {
                $tittel = $innslagService->opprettTittel($innslag);
            }
            // Hent tittel
            else {
                $tittel = $innslag->getTitler()->get($t_id);
            }

            // Sett standard-info
            $tittel->setTittel($tittelTekst);
            if ($innslag->getType()->harTid()) {
                $tittel->setVarighet($lengde);
            }

            switch ($innslag->getType()->getKey()) {
                    // Musikk
                case 'musikk':
                    $tittel->setSelvlaget($selvlaget == '1');
                    $tittel->setMelodiAv($melodiforfatter);

                    if ($sangtype == 'instrumental') {
                        $tittel->setInstrumental(true);
                    } else {
                        $tittel->setInstrumental(false);
                        $tittel->setTekstAv($tekstforfatter);
                    }
                    break;
                    // Teater
                case 'teater':
                    $tittel->setSelvlaget($selvlaget == '1');
                    $tittel->setTekstAv($tekstforfatter);
                    break;
                    // Dans
                case 'dans':
                    $tittel->setSelvlaget($selvlaget == '1');
                    $tittel->setKoreografi($koreografi);
                    break;
                    // Litteratur
                case 'litteratur':
                    $tittel->setTekstAv($tekstforfatter);
                    if ($leseopp == '1') {
                        $tittel->setLesOpp(true);
                    } else {
                        $tittel->setLesOpp(false);
                        $tittel->setVarighet(0);
                    }
                    break;
                    // Utstilling
                case 'utstilling':
                    $tittel->setType($type);
                    break;
            }

            $innslagService->lagreTitler($innslag, $tittel);
            
        } catch (Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        $response->setData(array($tittel));
        return $response;

    }


    /**
     * Slett en tittel fra innslaget
     */
    public function deleteTitleAction(Request $request)
    {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');
        
        // Hent data
        try{
            $data_arr = $this->getData($request, ['b_id', 't_id']);
            
            $b_id = $data_arr['b_id'];
            $t_id = $data_arr['t_id'];

            // Hent tittel
            $innslag = $innslagService->hent($b_id);
        }
        catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }
        
        
        // Fix #309 - brukere har fått "Finner ikke tittel XX i innslaget"-feil. Mulig fordi den allerede er slettet i en tidligere request.
        // Vi feiler gracefully her, med å late som om det var en vellykka sletting
        // Dersom noen tror de er lurere enn oss og prøver å fjerne en tittel fra et annet innslag vil det stå at det funka, men ikke gjøre det 😈
        // Vi logger denne feilen litt hardere, for å se om vi finner andre feil enn "Klarte ikke å finne tittel xx i innslag."
        try {
            $tittel = $innslag->getTitler()->get($t_id);
        } catch (Exception $e) {
            $this->get('logger')->error("Innslag:deleteTitle - Klarte ikke å hente tittel for sletting. Dette kan være at tittelen allerede er slettet, eller en grovere systemfeil. Brukeren har fått en hyggelig beskjed om at sletting funket. Feilkode: " . $e->getCode() . ", melding: " . $e->getMessage() . ".");
            
            $response->setStatusCode(JsonResponse::HTTP_OK);
            $response->setData($e->getMessage() . ' - Klarte ikke å hente tittel for sletting');
        }

        // Fjern tittelen
        try {
            $res = $innslagService->fjernTittel($innslag, $tittel);
            $response->setData(true);
            return $response;

        } catch (Exception $e) {
            $msg = "Klarte ikke å fjerne tittel " . $t_id . " fra innslag " . $b_id . ". Feilmelding: " . $e->getCode() . " - " . $e->getMessage();
            $this->get('logger')->error($msg);
            
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($msg);
            return $response;
        }

        return $response;
    }
    
}
