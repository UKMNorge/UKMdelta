<?php

namespace UKMNorge\UserBundle\Services;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Exception;
use UKMNorge\UserBundle\Entity\APIKeys;

class RedirectService {
	public function __construct ( $container ) {
		$this->container = $container;
	}

	public function doRedirect( ) {
		$session = $this->container->get('session');
		$keyRepo = $this->container->get('doctrine')->getRepository("UKMUserBundle:APIKeys");

		if( $session->get('rdirurl') ) {
            $rdirurl = $session->get('rdirurl');
            $key = $keyRepo->findOneBy(array('apiKey' => $rdirurl));
            if(!$key) {
                $errorMsg = 'DIPBundle: Ukjent sted å sende brukerdata til ('.$rdirurl.').';
                $this->container->get('logger')->error($errorMsg);
                die($errorMsg);
            }
            $rdirurl = $key->getApiReturnURL();

            // Unset redirect-session
            $session->remove('rdirurl');
			$session->remove('rdirtoken');

			return new RedirectResponse($rdirurl);
        }

        return new RedirectResponse($this->container->get('router')->generate('ukm_delta_ukmid_homepage'));
		
		
	}
}