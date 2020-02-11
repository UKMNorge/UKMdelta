<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

class DefaultController extends Controller
{

    public function indexAction()
    {	    
    	if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev' ) {
	        $this->ambURL = 'https://ambassador.ukm.dev/app_dev.php/dip/login';
	        $this->ambDipURL = 'https://ambassador.ukm.dev/app_dev.php/dip/receive/';
            $this->deltaFBLoginURL = 'https://delta.ukm.dev/app_dev.php/fblogin';
            $this->wordpressLoginURL = 'https://delta.ukm.dev/app_dev.php/wordpress-connect';
	    } 
	    else {
	        $this->ambURL = 'https://ambassador.ukm.no/dip/login';
	        $this->ambDipURL = 'https://ambassador.ukm.no/dip/receive/';
            $this->deltaFBLoginURL = 'https://delta.ukm.no/fblogin';
            $this->wordpressLoginURL = 'https://delta.ukm.no/ukmid/wordpress-connect';
	    }
	    
	    // If lastlocation-URL-parameter is set, we'll set a cookie.
	    // TODO: Burde vi i stedet bare lagre infoen i session, eller skal Marius bruke den til noe i WP? Da trenger vi heller ikke flere sjekker for å modifisere respons etc.
	    $request = Request::createFromGlobals();
	    if ( $request->query->get('lastlocation') ) {
	    	// TODO: Verify lastlocation is a valid kommune-ID.
	    	$lastlocationCookie = new Cookie("lastlocation", $request->query->get('lastlocation'));
	    }

	    $is_granted_user = $this->get('security.authorization_checker')->isGranted('ROLE_USER');
	    if( $is_granted_user ) {
	    	$response = new RedirectResponse( $this->get('router')->generate('ukm_delta_ukmid_homepage') );
	    	if ( $request->query->get('lastlocation') ) {
	    		$response->headers->setCookie($lastlocationCookie);
	    	}
		    return $response;
	    }
	    
	    $app_id = $this->getParameter('facebook_client_id');
	   
        $redirectURL = $this->deltaFBLoginURL;

	    $view_data = array();
	    $view_data['facebookLoginURL'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
        $view_data['wordpressLoginURL'] = $this->wordpressLoginURL;

        $response = $this->render('UKMDeltaBundle:Default:index.html.twig', $view_data);
        if( $request->query->get('lastlocation') ) {
        	$response->headers->setCookie($lastlocationCookie);
        }
        return $response;
    }
}
