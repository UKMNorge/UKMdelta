<?php
namespace UKMNorge\DeltaBundle\Services;

class SeasonService {
	public function __construct() {

	}

	public function getActive() {
		if (UKM_HOSTNAME == 'ukm.dev')
			return 2014;
		if( date('n') < 8) {
			return (int) date('Y');
		} 
		return (int) date('Y') +1;
	}

}


?>