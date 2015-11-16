<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\HttpFoundation\Request;

class FilmController extends Controller
{
	var $type = 'film';

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
}
?>