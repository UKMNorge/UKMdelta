<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

require_once('UKM/Autoloader.php');
use UKMNorge\Geografi\Kommune;

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
	    	    
	    $app_id = $this->getParameter('facebook_client_id');
	   
        $redirectURL = $this->deltaFBLoginURL;

	    $view_data = array();
	    $view_data['facebookLoginURL'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
        $view_data['wordpressLoginURL'] = $this->wordpressLoginURL;

        $response = $this->render('UKMDeltaBundle:Default:index.html.twig', $view_data);
        return $response;
    }

    /**
     * Setter en cookie med en kommune-ID, for å foreslå påmeldingskommune til brukeren.
     * Kommer som AJAX-request fra 
     *
     */
    public function lastLocationAction(Request $request, $kommune_id)
    {

        $response = new JsonResponse();
        $response->headers->set('Access-Control-Allow-Headers', 'true');
        $response->headers->set('Access-Control-Allow-Origin', 'https://' . $this->getParameter('UKM_HOSTNAME'));
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        $json = ['kommune' => $kommune_id];
        try {
            $kommune = new Kommune(intval($kommune_id));
            if ($kommune->getId() != false) {
                $response->headers->setCookie(
                    new Cookie(
                        "lastlocation",
                        $kommune->getId(),
                        time() + (2 * 365 * 24 * 60 * 60),
                        '/',
                        $this->getParameter('UKM_HOSTNAME'),
                        false
                    )
                );
                $json['success'] = true;
            } else {
                $json['success'] = false;
            }
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getCode();
            $json['message'] = $e->getMessage();
        }

        $response->setData($json);
        return $response;
    }
}
