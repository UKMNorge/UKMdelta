<?php

namespace UKMNorge\DeltaBundle\Controller;

require_once('UKM/Autoloader.php');

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use UKMNorge\Database\SQL\Insert;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Database\SQL\Update;

class DefaultController extends Controller
{

    public function indexAction()
    {

    	if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
	        $this->ambURL = 'https://ambassador.ukm.dev/app_dev.php/dip/login';
	        $this->ambDipURL = 'https://ambassador.ukm.dev/app_dev.php/dip/receive/';
	        $this->deltaFBLoginURL = 'https://delta.ukm.dev/web/app_dev.php/fblogin';
	    }
	    else {
	        $this->ambURL = 'https://ambassador.ukm.no/dip/login';
	        $this->ambDipURL = 'https://ambassador.ukm.no/dip/receive/';
	        $this->deltaFBLoginURL = 'https://delta.ukm.no/fblogin';
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

    public function foresatteAction($p_id, $p_mobil)
    {
      $view_data = array();
      $view_data['p_id'] = $p_id;
      $view_data['p_mobil'] = $p_mobil;
      return $this->render('UKMDeltaBundle:Default:foresatteinfo.html.twig', $view_data);
    }

    public function foresatteSaveAction($p_id, $p_mobil)
    {
      $view_data = array();
      $view_data['p_id'] = $p_id;
      $view_data['p_mobil'] = $p_mobil;
      $view_data['foresatteNavn'] = $this->get('request')->request->get('foresatteNavn');
      $view_data['foresatteMobil'] = $this->get('request')->request->get('foresatteMobil');


      $sql = new Query("SELECT * from `ukm_foresatte_info` WHERE `p_id` = '#p_id'", ['p_id' => $p_id]);
    //  var_dump($sql->debug());
      $numRows = Query::numRows($sql->run());
      if(1 == $numRows) {
        $sql = new Update('ukm_foresatte_info', ['p_id' => $p_id]);
      } else {
        $sql = new Insert('ukm_foresatte_info');
      }
      $sql->add('p_id', $p_id);
      $sql->add('foresatte_navn', $view_data['foresatteNavn']);
      $sql->add('foresatte_mobil', $view_data['foresatteMobil']);
      $result = $sql->run();

      if(false == $result || 0 == $result)
      {
        $this->addFlash('danger', 'Klarte ikke lagre informasjon om foresatte.');
        return $this->redirectToRoute('ukm_delta_foresatteinfo', ['p_id' => $p_id, 'p_mobil' => $p_mobil]);
      }


      return $this->render('UKMDeltaBundle:Default:takk.html.twig', $view_data);
    }
}
