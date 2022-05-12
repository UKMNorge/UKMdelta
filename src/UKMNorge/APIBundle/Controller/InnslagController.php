<?php

namespace UKMNorge\APIBundle\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

require_once('UKM/Autoloader.php');

class InnslagController extends SuperController {

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
            $innslag = $innslagService->hent($innslag_id);
            $arrangement = $innslag->getHome();

            $innslagArr = (array)$innslag;
            $innslagArr['erKunstgalleri'] = $arrangement->erKunstgalleri();
            $response->setData($innslagArr);
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
            $alle_innsag = array_reverse($innslagService->hentInnslagFraKontaktperson()->innslag);

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
            $innslag = $innslagFunctions->createInnslag($data_arr['k_id'], $data_arr['pl_id'], $data_arr['type'], $this, true);
            
            $response->setData(
                [
                    'innslag' => $innslag,
                    'path' => $this->generateUrl(
                        'ukm_delta_ukmid_pamelding_innslag_oversikt',
                        [
                            'k_id' => $innslag->kommune_id,
                            'pl_id' => $innslag->home_id,
                            'type' => $innslag->type->key,
                            'b_id' => $innslag->id

                        ]
                    )
                ]
            );
        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * _@route: <api/edit_innslag/>
     * Rediger innslag
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function editInnslagAction(Request $request) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');

        // Hent data
        try{
            $data_arr = $this->getData($request, ['b_id', 'navn'], ['beskrivelse', 'sjanger', 'tekniske_behov']);
            
            $innslag = $innslagService->hent($data_arr['b_id']);

            $innslag->setNavn($data_arr['navn']);
            $innslag->setBeskrivelse($data_arr['beskrivelse']);
            $innslag->setTekniskeBehov($data_arr['tekniske_behov']);
            
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
     * Fjern Innslag
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
                if(!$type->erJobbeMed()) {
                    $types[] = $type;
                }
            }

            $response->setData($types);

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

        /**
     * Hent innslag typer
     *
     * @param Int $pl_id
     * @return JsonResponse
     */
    public function getInnslagTypesJobbeMedAction(Int $pl_id) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');

        $types = [];
        
        try{
            $arrangement = $innslagService->hentArrangement($pl_id);
            foreach($arrangement->getInnslagTyper()->getAll() as $type) {
                if($type->erJobbeMed()) {
                    $types[] = $type;
                }
            }

            $response->setData($types);

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData($e->getMessage());
            return $response;
        }

        return $response;
    }

    /**
     * Lagrer alle endringer i et innslag
     * @return void
     */
    public function saveInnslagAction(Request $request) {
        $response = new JsonResponse();
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');


        // Hent data
        try{
            $data_arr = $this->getData($request, ['k_id', 'pl_id', 'type', 'b_id', 'navn'], ['beskrivelse', 'sjanger']);

            $b_id = $data_arr['b_id'];
            $navn = $data_arr['navn'];
            $beskrivelse = $data_arr['beskrivelse'];
            $sjanger = $data_arr['sjanger'];
            
            $route_data = [
                'k_id' => $data_arr['k_id'],
                'pl_id' => $data_arr['pl_id'],
                'type' => $data_arr['type'],
                'b_id' => $b_id
            ];

            // Hent inn innslaget
            $innslag = $innslagService->hent($b_id);

            if ($innslag->getType()->harBeskrivelse()) {
                $innslag->setBeskrivelse($beskrivelse);
            }

            // Hvis innslaget ikke har titler
            if ($innslag->getType()->erEnkeltperson()) {
                $person = $innslag->getPersoner()->getSingle();

                $innslag->setNavn($navn);

                $personService->lagre($person, $innslag->getId());
                $innslagService->lagre($innslag);
                
                $response->setData(
                    [
                        'saved' => $innslag->erPameldt(),
                        'mangler' => $innslag->getMangler(),
                        'path' => $this->generateUrl('ukm_delta_ukmid_pamelding_pameldt', $route_data)
                    ]
                );
                $response->setData();
                return $response;
            }

            // Innslaget har titler
            $innslag->setNavn($navn);
            if ($innslag->getType()->harSjanger()) {
                $innslag->setSjanger($sjanger);
            }

            $innslagService->lagre($innslag);
            
            $response->setData(
                [
                    'saved' => $innslag->erPameldt(),
                    'mangler' => $innslag->getMangler(),
                    'path' => $this->generateUrl('ukm_delta_ukmid_pamelding_pameldt', $route_data)
                ]
            );
            return $response;
            
        }catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_BAD_REQUEST);
            $response->setData($e->getMessage());
            return $response;
        }
    }
}