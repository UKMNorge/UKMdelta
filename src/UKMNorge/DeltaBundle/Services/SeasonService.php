<?php
namespace UKMNorge\DeltaBundle\Services;

class SeasonService {
	public function __construct($container) {
		$this->container = $container;
	}

	public function getActive() {
		#if ($this->container->getParameter('UKM_HOSTNAME') == 'ukm.dev')
		#	return 2018;
		if( date('n') < 8) {
			return (int) date('Y');
		} 
		return (int) date('Y') +1;
	}

}


?>