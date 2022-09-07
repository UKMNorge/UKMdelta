<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use UKMNorge\DeltaBundle\Entity\HideCampaign;
use UKMNorge\UserBundle\Services\UserService;
use UKMNorge\APIBundle\Services\InnslagService;
use UKMNorge\DeltaBundle\Services\SeasonService;

use DateTime;
use UKMmail;

require_once('UKMconfig.inc.php');

class DinSideController extends Controller
{
	private function _season() {
		$season = new SeasonService($this->container);
		return $season->getActive();
	}

	/**
	 * extrasAction
	 * Is rendered by the DinSide homepage view
	 * Return new Repsonse (html)
	**/
	public function extrasAction(Request $request)
	{
		$html = '';
		if( ($this->_season() == (UKM_HOSTNAME == 'ukm.dev' ? 2014 : 2017)) 
		 && (date('m') < 3 || date('m') == 12) )
		{
			$html .= $this->_losBandoAction()->getContent();
		}
		
		return new Response($html);
	}
	
	
	/********************************************************************************
	 * LOS BANDO CAMPAIGN
	*********************************************************************************/
	/**
	 * Show the form
	**/
	public function losBandoAction() {
        $userObj = new UserService($this->container);
		
		$user = $userObj->getCurrentUser();
		$contact_id = $user->getPameldUser();

		$view_data = [];
		$view_data['navn'] = $user->getName();
		$view_data['mobil'] = $user->getPhone();
		$view_data['epost'] = $user->getEmail();

		return $this->render('UKMDeltaBundle:DinSide:losbando-form.html.twig', $view_data);
	}
	/**
	 * Send the form
	**/
	public function losBandoSendAction(Request $request) {
		require_once('UKMconfig.inc.php');
		require_once('UKM/mail.class.php');
		
		$epost = $this->_losBandoEpost();
		$epost_spm = $epost; #str_replace('@', '+ukmspm@', $epost);
		$epost_sok = $epost; #str_replace('@', '+ukm@', $epost);
		
		$view_data['epost_spm'] = $epost_spm;
		$view_data['epost_sok'] = $epost_sok;
		
		$view_data['navn'] = $request->request->get('navn');
		$view_data['kontakt'] = $request->request->get('kontakt');
		$view_data['mobil'] = $request->request->get('mobil');
		$view_data['epost'] = $request->request->get('epost');
		$view_data['lenker'] = $request->request->get('lenker');
		$view_data['annet'] = $request->request->get('annet');
		
		$mail = $this->render('UKMDeltaBundle:DinSide:losbando-mail.html.twig', $view_data)->getContent();
		
		$epost = new UKMmail();
		$res1 = $epost->subject('UKM-band til Los Bando ('. $view_data['navn'] .')')
			  ->text( $mail )
			  ->to( $epost_sok )
			  ->ok();
		
		$res2 = $epost->to('support@ukm.no')->ok();

		$res3 = $epost->subject('DIN KOPI: UKM-band til Los Bando ('. $view_data['navn'] .')')
			  ->text( '<h1>Dette er din kopi av informasjonen vi har sendt til FilmBin</h1>'. $mail )
			  ->to( $view_data['epost'] )
			  ->ok();
	
		if( !$this->_hideCampaign('losbando') ) {
			$em = $this->getDoctrine()->getManager();
			$userObj = new UserService($this->container);
			$user = $userObj->getCurrentUser();
			$hide = new HideCampaign();
			$hide->setUserId( $user->getId() );
			$hide->setCampaign( 'losbando' );
			
			$em->persist( $hide );
			$em->flush();
		}
		
		return $this->render('UKMDeltaBundle:DinSide:losbando-thankyou.html.twig', $view_data);
	}

	/**
	 * Render info for the DinSide homepage
	**/
	private function _losBandoAction() {
		$userObj = new UserService($this->container);

		$user = $userObj->getCurrentUser();
		$contact_id = $user->getPameldUser();
		
		$innslagService = new InnslagService($this->container);
		$innslagsliste = $innslagService->hentInnslagFraKontaktperson($contact_id, $user->getId());
		
		$view_data = [];
		foreach( $innslagsliste as $gruppe => $alle_innslag ) {
			foreach( $alle_innslag as $innslag ) {
				if( $innslag->type == 'musikk' ) {
					$view_data['hideCampaign'] = $this->_hideCampaign('losbando');
					$epost = $this->_losBandoEpost();
					$view_data['epost_spm'] = $epost;#str_replace('@', '+ukmspm@', $epost);
					return $this->render('UKMDeltaBundle:DinSide:losbando.html.twig', $view_data );
				}
			}
		}
		return new Response('');
	}
	
	private function _losBandoEpost() {
		return 'losbando@filmbin.no';
	}
	
	private function _hideCampaign( $key ) {
		$userObj = new UserService($this->container);

		$user = $userObj->getCurrentUser();
		$em = $this->getDoctrine()->getManager()->getRepository('UKMDeltaBundle:HideCampaign');
		$hide = $em->findOneBy(array('campaign'=>$key, 'userId'=>$user->getId()));
		
		return $hide !== null;
	}
}