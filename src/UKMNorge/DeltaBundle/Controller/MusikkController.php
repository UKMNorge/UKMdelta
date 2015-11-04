<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use innslag;
use person;

class MusikkController extends Controller
{	
	var $type = 'musikk';

	public function whoAction($k_id, $pl_id) {
		$info = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> $this->type,
			'translationDomain' => $this->type
		);

		return $this->forward('UKMDeltaBundle:Innslag:who', $info);
	}

	public function createAction($k_id, $pl_id, $hvem) {
		$info = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> $this->type,
			'hvem'		=> $hvem
			);

		return $this->forward('UKMDeltaBundle:Innslag:create', $info);
	}

	

	public function saveOverviewAction($k_id, $pl_id, $b_id) {
		$view_data = array('k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);
		$request = Request::createFromGlobals();

        $innslagService = $this->get('ukm_api.innslag');

        $artistnavn = $request->request->get('artistnavn');
        $beskrivelse = $request->request->get('beskrivelse');
        
        $innslagService->lagreBeskrivelse($b_id, $beskrivelse);
        $innslagService->lagreArtistnavn($b_id, $artistnavn);

	    // Sjekk om alt er utfylt, og sett i så fall status til 8/9?
	    #TODO: Redircet til "fullfør og send inn påmelding"-steget
	    return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', $view_data);

	}
}