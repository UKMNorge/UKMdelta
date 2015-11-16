<?php
namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TeaterController extends Controller
{
	var $type = 'teater';

	public function whoAction($k_id, $pl_id) {
		$view_data = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> $this->type,
			'translationDomain' => $this->type
		);

		return $this->forward('UKMDeltaBundle:Innslag:who', $view_data);
	}

	public function createAction($k_id, $pl_id, $hvem) {
		$view_data = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> $this->type,
			'hvem'		=> $hvem,
			'translationDomain' => $this->type
		);

		return $this->forward('UKMDeltaBundle:Innslag:create', $view_data);
	}

	public function overviewAction ($k_id, $pl_id, $b_id) {
		$view_data = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'b_id'		=> $b_id,
			'type' 		=> $this->type,
			'translationDomain' => $this->type
		);

		return $this->render('ukm_delta_ukmid_pamelding_innslag_oversikt', $view_data);
	}


}
?>