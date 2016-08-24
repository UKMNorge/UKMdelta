<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use UKMNorge\UserBundle\Entity\DipToken;

class UKMDipController extends Controller {

	public function tokenAction() {
		try {
			$request = Request::createFromGlobals();

			$params = array();
			$signer = $this->get('UKM.urlsigner');

			$this->get('logger')->info('DIPBundle: tokenAction');

			// Dette er gamlemåten (ambassadør + RSVP)
			if(in_array($request->get('location'), array('ambassador', 'rsvp') ) ) {
				$this->get('logger')->info('DIPBundle: Location: '.$request->get('location'));
				
				if ($request->getMethod() == 'GET') {
					$params['token'] = $request->get('token');
					$params['location'] = $request->get('location');
					$sign = $request->get('sign');
				}
				else {
					// Fetch params
					$params['token'] = $request->request->get('token');
					$params['location'] = $request->request->get('location');
					$sign = $request->request->get('sign');
				}
			}
			else {

				if ($request->getMethod() == 'GET') {
					$params['token'] = $request->get('token');
					$params['api_key'] = $request->get('api_key');
					$sign = $request->get('sign');
				}
				else {
					// Fetch params
					$params['token'] = $request->request->get('token');
					$params['api_key'] = $request->request->get('api_key');
					$sign = $request->request->get('sign');
				}

				if(!array_key_exists('api_key', $params)) {
					// TODO: Dette burde håndteres i routeren, v2?
					$this->get('logger')->error('DIPBundle: api_key ikke funnet i request.');
					$this->get('logger')->error('DIPBundle: QUERY_STRING: '.$_SERVER['QUERY_STRING']);
					var_dump($params);
					die('COULD NOT FIND API KEY IN REQUEST');
				}

				if( !$signer->getApiKey( $request->get('api_key') ) ) {
					$this->get('logger')->error('DIPBundle: api_key '.$params['location'] . ' ikke funnet i databasen.');
					die('COULD NOT FIND API KEY IN DATABASE');
				}

				$this->get('logger')->info('DIPBundle: api_key: '.$params['api_key']);

				$signedURL = $signer->getSignedUrl($request->getMethod(), $params);
				if ( $sign != $signedURL) {
					$this->get('logger')->error('DIPBundle: Signert URL ('.$signedURL.') stemmer ikke med signering fra klient ('.$_SERVER['QUERY_STRING'].')');
					die('COULT NOT GRANT ACCESS');
				}

			}

			// Store received token in database
			#$repo = $this->getDoctrine()->getRepository('UKMUserBundle:DipToken');
			$dipToken = new DipToken();
			$dipToken->setToken($params['token']);
			// TODO: Fiks denne mer sikker
			if(array_key_exists('location', $params)) 
				$dipToken->setLocation($params['location']);
			else 
				$dipToken->setLocation($params['api_key']);

			// Update database with the new token
			$em = $this->getDoctrine()->getManager();
	    	$em->persist($dipToken);
	    	$em->flush();
			// Return an HTTP OK response
			$this->get('logger')->info('DIPBundle: Token stored OK.');
			$response = new Response('Token stored OK.');
		}
		catch (Exception $e) {
			$this->get('logger')->error('DIPBundle: En uventet feil skjedde. '.$e->getMessage());
			die('DIPBundle: En uventet feil skjedde. '.$e->getMessage());
		}
		return $response;
	}

}