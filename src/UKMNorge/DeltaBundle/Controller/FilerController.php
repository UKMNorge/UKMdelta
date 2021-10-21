<?php

namespace UKMNorge\DeltaBundle\Controller;
use UKMNorge\Innslag\Playback\Write;
use UKMNorge\Innslag\Titler\Write as WriteTitler;
use UKMNorge\Innslag\Titler\Utstilling;
use UKMNorge\Log\Logger;
use UKMNorge\Http\Curl;

use Exception;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;

require_once('UKM/Autoloader.php');

class FilerController extends Controller
{
    
    public function skjemaAction($innslag_id)
    {
        $innslagService = $this->container->get('ukm_api.innslag');
        /**
         * @var UKMNorge\Innslag\Innslag $innslag
         */
        $innslag = $innslagService->hent($innslag_id);
        $arrangement = $innslag->getHome();
        
        $curl_playback = new Curl();
        $curl_playback->timeout(2);
        $status_playback = $curl_playback->request('https://playback.' . UKM_HOSTNAME . '/api/status.php');

        return $this->render('UKMDeltaBundle:Filer:skjema.html.twig', ['status_playback' => $status_playback, 'innslag' => $innslag, 'arrangement' => $arrangement]);
    }


    public function saveAction($innslag_id)// for å lagre filen som er lastet opp
    {       
        $innslagService = $this->container->get('ukm_api.innslag');
        /**
         * @var UKMNorge\Innslag\Innslag $innslag
         */
        $innslag = $innslagService->hent($innslag_id);
        $arrangement = $innslag->getHome();

        $status = ['Kunne ikke laste opp filen', false];

        
        if( isset( $_POST['playback_id'] ) ) {
            try {
                $playback = $innslag->getPlayback()->get(intval($_POST['playback_id']));
                $playback->setNavn($_POST['name']);
                $playback->setBeskrivelse($_POST['description']);
                Write::lagre($playback);
                $status = ["Oppdatering av navn og beskrivelse er lagret.", true];
            } catch( Exception $e ) {
                // UKMplayback::getFlash()->error('Kunne ikke lagre endringene.');
                $status = ['Kunne ikke lagre endringene: ' . $e->getMessage(), false];
            }
        } else {
            try {
                $userId = $this->get('ukm_user')->getCurrentUser()->getId();
                $this->_setupLogger($userId, $arrangement->getId());

                $playback = Write::opprett( $arrangement, $innslag->getId(), $_POST['filename'], $_POST['name'], $_POST['description']);
                
                // Kobling mellom playback og utstilling
                if($arrangement->erKunstgalleri() && isset($_POST['tittelkunstverk']) && $_POST['tittelkunstverk'] != "") {
                    $tittelUtstilling = $innslag->getTitler()->get($_POST['tittelkunstverk']);

                    $tittelUtstilling->setPlaybackId($playback->getId());

                    WriteTitler::save($tittelUtstilling);
                }

                $status = ['Filen er lastet opp!', true];
            } catch( Exception $e ) {
                $status = ['Kunne ikke laste opp filen:' . $e->getMessage(), false];
            }
        }
        
        $this->addFlash($status[1] ? "success" : "danger", $status[0]); // Fra Controller super klasse

        return $this->skjemaAction($innslag_id);
    }

    public function deleteAction($innslag_id, $delete_id) {     
        $innslagService = $this->container->get('ukm_api.innslag');
        /**
         * @var UKMNorge\Innslag\Innslag $innslag
         */
        $innslag = $innslagService->hent($innslag_id);
        
        $arrangement = $innslag->getHome();
        $playback = $innslag->getPlayback()->get($delete_id);


        // Fjern kobling på utstilling på bilde og playback
        if($arrangement->erKunstgalleri()) {
            if($innslag->getType()->getKey() == 'utstilling') {
                foreach($innslag->getTitler()->getAll() as $utstilling ){
                    if($utstilling->getPlaybackId() == $delete_id) {
                        // Setup logger
                        $userId = $this->get('ukm_user')->getCurrentUser()->getId();
                        $this->_setupLogger($userId, $arrangement->getId());
                        
                        // Fjern kobling på bilde og playback for Utstilling (Tittel)
                        $utstilling->setBildeId(-1);
                        $utstilling->setPlaybackId(-1);
                        WriteTitler::save($utstilling);
                    }
                }
            }
        }

        $status = ['Filen kan ikke slette filen', false];
        
        if( $playback ) {
            try {
                Write::slett( $arrangement, $playback);
                $status = ['Mediefilen er nå slettet fra '. $innslag->getNavn(), true];
            } catch( Exception $e ) {
                $status = ['En ukjent feil gjorde at den valgte lydfilen for "'. $innslag->getNavn().'" ikke ble slettet', false];
            }	
        }

        $this->addFlash($status[1] ? "success" : "danger", $status[0]); // Fra Controller super klasse

        return $this->redirectToRoute(
            'ukm_mediefil_skjema',
            [
                'innslag_id' => $innslag_id            
            ]
        );
    }

    /**
     * Setup UKM Logger
     *
     * @param Int $userId
     * @param Int $arrangement_id
     * @return void
     */
    private function _setupLogger(Int $userId, Int $arrangement_id)
    {
        Logger::setID('delta', $userId, $arrangement_id);
    }
}

