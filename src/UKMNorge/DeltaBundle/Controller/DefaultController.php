<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {

	    $userService = $this->get('ukm_user');#->getCurrentUser();
	
		if( $userService->gotAccess('remembered', $userService->getCurrentUser() ) ) {
			return $this->redirect( $this->get('router')->generate('ukm_delta_ukmid_homepage') );
		}

        return $this->render('UKMDeltaBundle:Default:index.html.twig', array());
    }
}
