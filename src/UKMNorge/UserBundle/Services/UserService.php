<?php
namespace UKMNorge\UserBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use stdClass;

class UserService {
	
	var $levels = array('remembered'=> 'IS_AUTHENTICATED_REMEMBERED',
						'fully'		=> 'IS_AUTHENTICATED_FULLY',
						'anonymous'	=> 'IS_AUTHENTICATED_ANONYMOUSLY'
						);
						
	public function __construct( $container ) {
		$this->container = $container;
		$this->security = $this->container->get('security.context');
	}
	
	public function getCurrentUser() {
        $user = $this->security->getToken()->getUser();

        if( $user == 'anon' ) {
            throw new Exception(
                'User currently not logged in.',
                100
            );
        }
		return $user;
	}
	
	public function gotAccess( $level ) {
		if( !isset( $this->levels[ $level ] ) ) {
			throw new Exception('Gitt brukertilgang av ukjent nivå ('.$level.'). Kan ikke gi tilgang');
		} else {
			$security_level = $this->getLevel( $level );
		}
		
		return $this->security->isGranted('IS_AUTHENTICATED_REMEMBERED');
	}
	
	public function getLevel( $level ) {
		return $this->levels[ $level ];
	}
}