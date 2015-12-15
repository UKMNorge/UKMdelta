<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use UKMNorge\UserBundle\Entity\DipToken;

class UKMDipController extends Controller {

	public function tokenAction() {
		$request = Request::createFromGlobals();

		// Only if request comes from safe source
		// if ($this->getRequest()->get('REMOTE_ADDR') != 'amb.ukm.dev') {
		// 	die('Access only from authorized sites!');
		// }
		//var_dump($this->getRequest());
		if ($request->getMethod() == 'GET') {
			$token = $request->get('token');
			$location = $request->get('location');
		}
		else {
			// Fetch params
			$token = $request->request->get('token');
			$location = $request->request->get('location');
		}
		// var_dump($token);
		// var_dump($location);
		
		// Store received token in database
		#$repo = $this->getDoctrine()->getRepository('UKMUserBundle:DipToken');
		$dipToken = new DipToken();
		$dipToken->setToken($token);
		$dipToken->setLocation($location);

		// Update database with the new token
		$em = $this->getDoctrine()->getManager();
    	$em->persist($dipToken);
    	$em->flush();
		// Return an HTTP OK response
		$response = new Response('Token stored OK.');
		return $response;
	}
}