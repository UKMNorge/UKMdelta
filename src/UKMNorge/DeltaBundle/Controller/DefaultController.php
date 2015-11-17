<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {	    
	    $is_granted_user = $this->get('security.authorization_checker')->isGranted('ROLE_USER');
	    if( $is_granted_user ) {
		    return $this->redirect( $this->get('router')->generate('ukm_delta_ukmid_homepage') );
	    }

        return $this->render('UKMDeltaBundle:Default:index.html.twig', array());
    }
}
