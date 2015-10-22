<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


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
}