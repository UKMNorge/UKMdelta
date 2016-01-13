<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{

    public function indexAction()
    {	    

    	if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
	        $this->ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/login';
	        $this->ambDipURL = 'http://ambassador.ukm.dev/app_dev.php/dip/receive/';
	        $this->deltaFBLoginURL = 'http://delta.ukm.dev/web/app_dev.php/fblogin';
	    } 
	    else {
	        $this->ambURL = 'http://ambassador.ukm.no/dip/login';
	        $this->ambDipURL = 'http://ambassador.ukm.no/dip/receive/';
	        $this->deltaFBLoginURL = 'http://delta.ukm.no/fblogin';
	    }
	    
	    $is_granted_user = $this->get('security.authorization_checker')->isGranted('ROLE_USER');
	    if( $is_granted_user ) {
		    return $this->redirect( $this->get('router')->generate('ukm_delta_ukmid_homepage') );
	    }
	    
	    $app_id = $this->getParameter('facebook_client_id');
	   
        $redirectURL = $this->deltaFBLoginURL;

	    $view_data = array();
	    $view_data['facebookLoginURL'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';

        return $this->render('UKMDeltaBundle:Default:index.html.twig', $view_data);
    }
}
