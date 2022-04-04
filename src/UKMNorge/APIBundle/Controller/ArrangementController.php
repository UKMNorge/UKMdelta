<?php

namespace UKMNorge\APIBundle\Controller;

use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use UKMNorge\Nettverk\Omrade;

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
}