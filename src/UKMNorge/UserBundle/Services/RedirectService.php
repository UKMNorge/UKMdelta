<?php

namespace UKMNorge\UserBundle\Services;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Exception;
use DateTime;
use UKMCURL;
use UKMNorge\UserBundle\Entity\APIKeys;

class RedirectService {
	public function __construct ( $container ) {
		$this->container = $container;
		$this->logger = $container->get('logger');
	}

	public function doRedirect( ) {
		$session = $this->container->get('session');
		$keyRepo = $this->container->get('doctrine')->getRepository("UKMUserBundle:APIKeys");

		if( $session->get('rdirurl') ) {
            $rdirurl = $session->get('rdirurl');
            $key = $keyRepo->findOneBy(array('apiKey' => $rdirurl));
            if(!$key) {
                $errorMsg = 'DIPBundle: Ukjent sted å sende brukerdata til ('.$rdirurl.').';
                $this->logger->error($errorMsg);
                throw new Exception($errorMsg);
            }
            $rdirurl = $key->getApiReturnURL();

            // Send godkjenning til tjenesten, om noen.
            $token = $session->get('rdirtoken');
            $this->defaultPoster($token, $key);

            // Unset redirect-session
            $session->remove('rdirurl');
			$session->remove('rdirtoken');

			return new RedirectResponse($rdirurl);
        }
		
		$target_path = $session->get('_security.ukm_delta_wall.target_path');
		$session->remove('_security.ukm_delta_wall.target_path');
		if( $target_path ) {
			return new RedirectResponse( $target_path );
		}

        return new RedirectResponse($this->container->get('router')->generate('ukm_delta_ukmid_homepage'));	
	}

	/**
	 * Denne funksjonen sender brukerdata til riktig tjeneste.
	 * (Må gjøres før redirecting!)
	 *
	 * @param string $token
	 * @param APIKeys $api_key
	 *
	 * @return void or throws Exception
	 */
	private function defaultPoster($token, $api_key) {
        require_once('UKM/curl.class.php');
        
        $this->logger->info('DIPBundle: Selecting user-data to POST.');

        $user = $this->container->get('ukm_user')->getCurrentUser();

        // Set more token-info
        $doctrine = $this->container->get('doctrine');
        $repo = $doctrine->getRepository("UKMUserBundle:DipToken");
        $dbToken = $repo->findOneBy(array('token' => $token));
        $dbToken->setTimeUsed(new DateTime());
        $dbToken->setUserId($user->getId());

        $doctrine->getManager()->persist($dbToken);
        $doctrine->getManager()->flush();

        // Encode brukerdata og token til JSON-objekt
        $json = array();
        $json['token'] = $token;
        $json['delta_id'] = $user->getId();
        $json['email'] = $user->getEmail();
        $json['phone'] = $user->getPhone();
        $json['address'] = $user->getAddress();
        $json['post_number'] = $user->getPostNumber();
        $json['post_place'] = $user->getPostPlace();
        $json['birthdate'] = $user->getBirthdate();
        $json['facebook_id'] = $user->getFacebookId();
        $json['facebook_id_unencrypted'] = $user->getFacebookIdUnencrypted();
        $json['facebook_access_token'] = $user->getFacebookAccessToken();
        $json['first_name'] = $user->getFirstName();
        $json['last_name'] = $user->getLastName();
        $json['kommune_id'] = $user->getKommuneId();

        $json = json_encode($json);
        
        // Send brukerinfo til gitt sted
        $this->logger->info('DIPBundle: Curling user-data to '. $api_key->getApiKey() . ' ('.$api_key->getApiTokenURL() .')');
        $curl = new UKMCurl();
        $curl->post(array('json' => $json));
        // Res skal være et JSON-objekt.
        $res = $curl->process($api_key->getApiTokenURL());
        $this->logger->info('DIPBundle: Curl-respons: '.var_export($res, true));
        if(!is_object($res)) {
            $this->logger->error('DIPBundle: Tjenesten '.$api_key->getApiKey() .' svarte ikke med en godkjent status!');
            $errorMsg = 'Tjenesten du prøvde å logge inn på klarte ikke å ta i mot brukerinformasjonen din. Dette er en systemfeil, ta kontakt med UKM Support hvis feilen fortsetter.';
            throw new Exception($errorMsg);
        }
        if(!$res->success) {
            $this->logger->error('DIPBundle: Tjenesten '.$api_key->getApiKey() .' svarte med success == false!');
            $errorMsg = 'Tjenesten du prøvde å logge inn på klarte ikke å ta i mot brukerinformasjonen din. Dette er en systemfeil, ta kontakt med UKM Support hvis feilen fortsetter.';
            throw new Exception($errorMsg);
        }
    }
}