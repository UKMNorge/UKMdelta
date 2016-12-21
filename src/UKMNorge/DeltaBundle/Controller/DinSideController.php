<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;

class DinSideController extends Controller
{
	public function extrasAction()
	{
		$html = '';
		
		return new Response($html);
	}
	
	
	private function _season() {
		return $this->get('ukm_delta.season')->getActive();
	}
}