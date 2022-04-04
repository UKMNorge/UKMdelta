<?php

namespace UKMNorge\APIBundle\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


require_once('UKM/Autoloader.php');

class PersonController extends SuperController {
        
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
}
