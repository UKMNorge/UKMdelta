<?php

namespace UKMNorge\UserBundle\Services;


use UKMNorge\UserBundle\Entity\APIKeysRepository;

class URLSignerService {


	public function __construct($doctrine) {
		$this->doctrine = $doctrine;
	}
	/**
	 * $method = GET|POST
	 * $array = [key1 => val1, key2 => val2, ]
	**/
	public function getSignedUrl( $method, $array ) {
		ksort( $array );

		$concat = strtoupper( $method ).'?'.http_build_query( $array );
		$sign = md5( $array['api_key'] . $concat . $this->getApiSecret($array) );

		#return $concat.'&sign='.$sign;
		return $sign;
	}

	public function getApiKey($api_key) {
		$repo = $this->doctrine->getRepository("UKMUserBundle:APIKeys");
		$key = $repo->findOneBy(array('apiKey' => $api_key));
		if( null == $key ) {
			throw new Exception("Fant ikke API-key: ".$api_key);
		}
		return $key->getApiKey();
	}

	private function getApiSecret($params) {

		$repo = $this->doctrine->getRepository("UKMUserBundle:APIKeys");
		$key = $repo->findOneBy(array('apiKey' => $params['api_key']));
		if( null == $key ) {
			throw new Exception("Fant ikke API-key: ".$api_key);
		}
		return $key->getApiSecret();
	}
}