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

class DefaultController extends Controller
{

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


    /* ---------------------------- Innslag ---------------------------- */

    /**
     * _@route: <api/innslag/>
     * hent 1 innslag ved bruke av innslag id
     * 
     * @return JsonResponse
     */
    public function getSingleInnslagAction($innslag_id) {
        $response = new JsonResponse(); 
        $innslagService = $this->get('ukm_api.innslag');

        // Hent data
        try{
            $response->setData($innslagService->hent($innslag_id));
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }
        return $response;
    }

    
    /**
     * _@route: <api/get_all_innslag/>
     * hent alle innslag
     * 
     * @return JsonResponse
     */
    public function getAllInnslagAction() {
        $response = new JsonResponse(); 
        $innslagService = $this->get('ukm_api.innslag');

        // Hent data
        try{
            $alle_innsag = $innslagService->hentInnslagFraKontaktperson()->innslag;
            
            $fullforte_innslag = [];
            $ikke_fullforte_innslag = [];
            foreach($alle_innsag as $innslag) {
                $personer = [];
                $innslag->getPersoner()->getAll();
                foreach($innslag->getPersoner() as $p) {
                    $personer[] = $p;
                }

                if($innslag->getHome()->erFerdig()) {
                    // Do nothing
                }
                else if($innslag->erPameldt()) {
                    $fullforte_innslag[] = array('innslag' => $innslag, 'personer' => $personer);
                }
                else if(!$innslag->erPameldt()) {
                    $ikke_fullforte_innslag[] = array('innslag' => $innslag, 'personer' => $personer);
                }
            }
            $response->setData([
                'fullforte' => $fullforte_innslag ,
                'ikke_fullforte' => $ikke_fullforte_innslag]
            );


        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
       
    }
    
    /**
     * _@route: <api/new_innslag/>
     * Opprett innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function newInnslagAction(Request $request) {
        $response = new JsonResponse();
        $innslagFunctions = $this->get('ukm_delta.innslagfunctions');

        // Hent data
        try{
            $data_arr = $this->getData($request, ['k_id', 'pl_id', 'type']);
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        // Kjør opprett innslag
        try{
            $response->setData($innslagFunctions->createInnslag($data_arr['k_id'], $data_arr['pl_id'], $data_arr['type'], $this, true));
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
        }

        return $response;
    }

    /**
     * _@route: <api/edit_innslag/>
     * Opprett innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function editInnslagAction(Request $request) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');

        // Hent data
        try{
            $data_arr = $this->getData($request, ['b_id', 'navn'], ['beskrivelse', 'sjanger']);
            
            $innslag = $innslagService->hent($data_arr['b_id']);

            $innslag->setNavn($data_arr['navn']);
            $innslag->setBeskrivelse($data_arr['beskrivelse']);
            
            if($data_arr['sjanger']) {
                $innslag->setSjanger($data_arr['sjanger']);
            }

            $res = $innslagService->lagre($innslag);
            $response->setData($res);

        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

    /**
     * _@route: <api/get_innslag/>
     * Opprett innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function removeInnslagAction(Request $request) {
        $innslagService = $this->get('ukm_api.innslag');
        $response = new JsonResponse();

        // Hent data
        try{
            $data_arr = $this->getData($request, ['pl_id', 'b_id']);
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }

        try {
            $innslag = $innslagService->hent($data_arr['b_id']);
            $innslagService->meldAv($innslag->getId(), $data_arr['pl_id']);
            $response->setData(['success' => $this->get('translator')->trans('removeAction.fjernet', ["%name" => $innslag->getNavn()], 'base')]);
        } catch (Exception $e) {
            $this->get('logger')->error("Klarte ikke å melde av innslag " . $data_arr['b_id'] . ". Feilmelding: " . $e->getCode() . " - " . $e->getMessage());
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
        }

        return $response;
    }


    /**
     * Hent innslag typer
     *
     * @param Int $pl_id
     * @return JsonResponse
     */
    public function getInnslagTypesAction(Int $pl_id) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');

        $types = [];
        
        try{
            $arrangement = $innslagService->hentArrangement($pl_id);

            foreach($arrangement->getInnslagTyper()->getAll() as $type) {
                $types[] = $type;
            }

            $response->setData($types);

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }


    /* ---------------------------- Arrangement ---------------------------- */

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
            $response->setData($e->getMessage());
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

            $arrangementer_arr[] = $arrangement;
        }

        try{
            $response->setData($arrangementer_arr);
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
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

    /**
     * Legg til en ny tittel
     * Handler for POST (lagre for)
     *
     * @param Int $k_id
     * @param Int $pl_id
     * @param String $type
     * @param Int $b_id
     */
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

    public function lagreTekniskeBehov(Request $request) {
        try {
            $data_arr = $this->getData($request, ['b_id', 'teknisk']);
            
            $b_id = $data_arr['b_id']; // innslag id
            $teknisk = $data_arr['teknisk']; // teknisk

            $request = Request::createFromGlobals();

            $innslagService = $this->get('ukm_api.innslag');
            $innslag = $innslagService->hent($b_id);
            
            // Set teknisk
            $innslag->setTekniskeBehov($teknisk);
            $innslagService->lagre($innslag);

        } catch (Exception $e) {
            $this->get('logger')->errror("UKMDeltaBundle:Innslag:saveTechnical - Klarte ikke å lagre tekniske behov. Feilkode: " . $e->getCode() . ". Melding: " . $e->getMessage(), $route_data);
            $this->addFlash("danger", "Klarte ikke å lagre tekniske behov");
        }

        return null;
    }


    /* ---------------------------- Other Methods ---------------------------- */

    public function hentCurrentUser()
    {
        return $this->get('ukm_user')->getCurrentUser();
    }
    
    /**
     * Hent arrangement
     *
     * @param JsonResponse $request
     * @param array $arr_key
     * @return array
     */
    private function getData($request, $arr_key, $arr_key_optional = []) {
        $arr_data = [];
        foreach ($arr_key as $key) {
            $data = $request->request->get($key);
            if(empty($data)) {
                throw new Exception($key . ' is not provided');
            }
            $arr_data[$key] = $data;
        }

        foreach ($arr_key_optional as $optional_key) {
            $data = $request->request->get($optional_key);
            $arr_data[$optional_key] = empty($data) ? null : $data;
        }
        

        return $arr_data;
    }
    
}
