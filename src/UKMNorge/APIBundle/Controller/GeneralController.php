<?php

namespace UKMNorge\APIBundle\Controller;

use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

require_once('UKM/Autoloader.php');

class GeneralController extends SuperController {
    /**
     * Sjekk hvilken info vi har om brukeren, og send til riktig
     * side (path) for å innhente det vi mangler
     *
     * @return JsonResponse
     */
    public function checkInfoAction()
    {
        $response = new JsonResponse();

        try{
            $user = $this->hentCurrentUser();
            
            if( $user->getBirthdate() == null ) {
                // Gå til spørsmål om alder
                $response->setData(
                    [
                        'validation' => false,
                        'path' => $this->generateUrl('ukm_delta_ukmid_alder')
                    ]
                );
                return $response;
            } else {
                // Deltakere under 15, som tidligere har oppgitt alder,
                // men ikke oppgitt foresatte må innom alder og foresatt-siden på nytt
                $now = new DateTime('now');
                $age = $user->getBirthdate()->diff($now)->y;
                
                if( $age < 15 && $user->getForesattMobil() == null ) {
                    $response->setData(
                        [
                            'validation' => false,
                            'path' => $this->generateUrl('ukm_delta_ukmid_alder')
                        ]
                    );
                    return $response;
                }
            }
    
            if( $user->getSamtykke() === null ) {
                $response->setData(
                    [
                        'validation' => false,
                        'path' => $this->generateUrl('ukm_delta_ukmid_personvern')
                    ]
                );
                return $response;
            }
    
            if( $this->get('session')->has('checkInfoRedirect') ) {
                $k_id = $this->get('session')->get('checkInfo_kid');
                $pl_id = $this->get('session')->get('checkInfo_plid');
                
                $response->setData(
                    [
                        'validation' => false,
                        'path' => $this->generateUrl('ukm_delta_ukmid_personvern', [$k_id, $pl_id])
                    ]
                );
                return $response;
            }

        } catch(Exception $e) {
            $response->setStatusCode(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            $response->setData(['errorMessage' => $e->getMessage()]);
            return $response;
        }
        
        $response->setData(
            [
                'validation' => true,
                'path' => ''
            ]
        );
        return $response;
    }
}