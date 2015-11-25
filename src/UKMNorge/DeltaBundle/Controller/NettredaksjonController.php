<?php
namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class NettredaksjonController extends Controller
{
	var $type = 'nettredaksjon';

	public function createAction($k_id, $pl_id) {
		$view_data = array(
			'k_id' 		=> $k_id,
			'pl_id' 	=> $pl_id, 
			'type' 		=> $this->type,
			'translationDomain' => $this->type
		);

		return $this->forward('UKMDeltaBundle:Innslag:create_tittellos', $view_data);
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