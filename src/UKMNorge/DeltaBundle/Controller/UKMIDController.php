<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class UKMIDController extends Controller
{
    public function indexAction()
    {
	    $view_data = array();
	    
	    $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }
}
