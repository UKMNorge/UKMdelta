<?php
namespace UKMNorge\APIBundle\Services;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UKMNorge\Geografi\Fylker;
use UKMNorge\Geografi\Kommune;

require_once('UKM/Autoloader.php');

class GeografiService {
	
	public function __construct(ContainerInterface $container) {
		$this->container = $container;
    }

    public function hentKommune( Int $kommuneID ) {
        try {
            $kommune = new Kommune( $kommuneID );
        } catch(Exception $e) {
            if($e->getCode() == 102001) {
                $kommune = null;
            } else {
                throw $e;
            }
        }
        return $kommune;
    }

    public function hentFylke( Int $fylkeID ) {
        return Fylker::getById( $fylkeID );
    }
}