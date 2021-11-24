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
        }

        try{
            $response->setData($arrangementer);
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
            $fylker[$fylke->getId()] = $fylke;

            $kommuner_arr = [];
            foreach($fylke->getKommuner()->getAll() as $kommune) {

                $kommuner_arr[] = [
                    "id" => $kommune->getId(),
                    "navn" => $kommune->getNavn(),
                    'erAktiv' => $kommune->erAktiv(),
                    'action' => $kommune->getAttr('action'),
                    'link' => $kommune->getLink(),
                ];
            }

            $fylker[$fylke->getId()]->kommuner = $kommuner_arr;
            
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
    private function getData($request, $arr_key) {
        $arr_data = [];
        foreach ($arr_key as $key) {
            $data = $request->request->get($key);
            if(empty($data)) {
                throw new Exception($key . ' is not provided');
            }
            $arr_data[$key] = $data;
        }

        return $arr_data;
    }
    
}
