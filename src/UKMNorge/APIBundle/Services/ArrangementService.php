<?php
namespace UKMNorge\APIBundle\Services;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UKMNorge\Arrangement\Arrangement;

require_once('UKM/Autoloader.php');

class ArrangementService {
	
	public function __construct(ContainerInterface $container) {
		$this->container = $container;
    }

    public function hent( Int $arrangementId ) {
        return new Arrangement( $arrangementId );
    }
}