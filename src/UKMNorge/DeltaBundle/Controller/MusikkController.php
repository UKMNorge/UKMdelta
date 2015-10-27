<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use innslag;
use person;

class MusikkController extends Controller
{
	public function whoAction($k_id, $pl_id) {
		$info = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> 'musikk',
			'knapp1' 	=> "Jeg spiller alene",
			'knapp2' 	=> "Jeg spiller sammen med noen"
		);

		return $this->forward('UKMDeltaBundle:Innslag:who', $info);
	}

	public function createAction($k_id, $pl_id, $hvem) {
		$info = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> 'musikk',
			'hvem'		=> $hvem
			);

		return $this->forward('UKMDeltaBundle:Innslag:create', $info);
	}

	public function newPersonAction($k_id, $pl_id, $b_id) {
		$view_data = array();

		$view_data['k_id'] = $k_id;
		$view_data['pl_id'] = $pl_id;
		$view_data['b_id'] = $b_id;
		
		return $this->render('UKMDeltaBundle:Musikk:nyPerson.html.twig', $view_data);
		

	}

	public function editPersonAction($k_id, $pl_id, $b_id, $p_id) {
		$view_data = array();

		// TODO: Hent data fra database (PersonService), ikke UserBundle

		$user = $this->get('ukm_user')->getCurrentUser();
		$personService = $this->get('ukm_api.person');
		$innslagService = $this->get('ukm_api.innslag');
		$innslag = $innslagService->hent($b_id);
		$person = $personService->hent($p_id);
		foreach($innslag->personer() as $personInfo) {
			// Send kun den personinfoen som stemmer med den personen man vil redigere
			if ($personInfo['p_id'] == $p_id) {
				$innslagsPerson = $personInfo;
			}
		}
		// var_dump($person);
		// var_dump($innslagsPerson);

		$view_data['k_id'] = $k_id;
		$view_data['pl_id'] = $pl_id;
		$view_data['b_id'] = $b_id;
		$view_data['p_id'] = $p_id;
		$view_data['user'] = $user;
		$view_data['person'] = $person;
		$view_data['innslag'] = $innslagsPerson;
		$view_data['age'] = $personService->alder($person);
		return $this->render('UKMDeltaBundle:Musikk:redigerPerson.html.twig', $view_data);
	}

	public function saveNewPersonAction($k_id, $pl_id, $b_id) {
		// var_dump($k_id);
		// var_dump($pl_id);
		// var_dump($b_id);
		// Ta imot post-variabler
        $request = Request::createFromGlobals();

        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');	

        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $alder = $request->request->get('alder');
        $instrument = $request->request->get('instrument');
        $mobil = $request->request->get('mobil');

        echo '<br>saveNewPersonAction():<br/>';
        var_dump($fornavn);
        var_dump($etternavn);
        var_dump($mobil);
        var_dump($alder);
        var_dump($instrument);



        // Hent personobjekt om deltakeren finnes, opprett en ny en hvis ikke.
        $person = $personService->opprett($fornavn, $etternavn, $mobil, $pl_id);
        $p_id = $person->get('p_id');

        $innslagService->leggTilPerson($b_id, $p_id);
        $innslagService->lagreInstrument($b_id, $p_id, $pl_id, $instrument);
        $personService->lagreAlder($p_id, $pl_id, $alder);
        $personService->lagreMobil($p_id, $pl_id, $mobil);

		return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id));
	}

	public function savePersonAction($k_id, $pl_id, $b_id, $p_id) {
		// Ta imot post-variabler
        $request = Request::createFromGlobals();

        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');	

        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $alder = $request->request->get('alder');
        $instrument = $request->request->get('instrument');
        $mobil = $request->request->get('mobil');

        // Sjekk inputs?

        $innslagService->lagreInstrument($b_id, $p_id, $pl_id, $instrument);
        $personService->lagreFornavn($p_id, $pl_id, $fornavn);
        $personService->lagreEtternavn($p_id, $pl_id, $etternavn);
        $personService->lagreAlder($p_id, $pl_id, $alder);
        $personService->lagreMobil($p_id, $pl_id, $mobil);

		return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id));
	}
	public function overviewAction($k_id, $pl_id, $b_id) {
		require_once('UKM/innslag.class.php');

		$view_data = array();
		$user = $this->get('ukm_user')->getCurrentUser();
		// Hent data om innslaget 
		$innslagService = $this->get('ukm_api.innslag');
		$personService = $this->get('ukm_api.person');
		$innslag = $innslagService->hent($b_id);
		// Legg data fra innslaget i variabler som kan jobbes med enklere i twig
		
		$personer = $innslag->personer();
		foreach ($personer as &$person) {
			$person['age'] = $personService->alder($personService->hent($person['p_id']));
		}
		$titler = $innslag->titler($pl_id); 


		var_dump($personer);
		var_dump($innslag); 

		$view_data['k_id'] = $k_id;
		$view_data['pl_id'] = $pl_id;
		$view_data['b_id'] = $b_id;
		$view_data['user'] = $user;
		$view_data['name'] = $innslag->info['b_name'];
		$view_data['innslag'] = $innslag->info;
		$view_data['personer'] = $personer;
		$view_data['titler'] = $titler;
		
		return $this->render('UKMDeltaBundle:Musikk:oversikt.html.twig', $view_data);
	}

	public function saveOverviewAction($k_id, $pl_id, $b_id) {
		$request = Request::createFromGlobals();

        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');



	}
}