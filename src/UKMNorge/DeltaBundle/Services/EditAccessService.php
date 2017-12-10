<?php
namespace UKMNorge\DeltaBundle\Services;

use innslag_v2;
use GrantAccess;
use DateTime;

// Bestemmer om en UKMID-bruker skal ha tilgang til å redigere et innslag, og hvis ikke setter den opp alt som trengs for å be om det.
class EditAccessService {

	private $innslag = null;
	private $current_user = null;
	private $container = null;

	public function __construct($container) {
		$this->container = $container;
		$this->current_user = $this->container->get('ukm_user')->getCurrentUser();
	}

	/**
	 * Inngangspunkt - sjekker alle krav for tilgang og returerner enten true eller false om brukeren skal få redigere innslaget.
	 *
	 */
	public function hasEditAccess($b_id) {
		$this->innslag = new innslag_v2($b_id);

		if ( $this->isContact() ) {
			#return true;
		}

		if ( $this->hasBeenGrantedEditAccess() ) {
			return true;
		}
		return false;
	}

	/**
	 * Returnerer true om brukeren er kontaktperson for innslaget, false hvis ikke.
	 *
	 */
	public function isContact($b_id = null) {
		if ($b_id != null) {
			$innslag = new innslag_v2($b_id);
		}
		else {
			$innslag = $this->innslag;
		}

		if ( $innslag->getKontaktpersonId() == $this->current_user->getPameldUser() ) {
			return true;
		}
	
		return false;
	}

	/** 
	 * Returnerer true dersom brukeren har fått egen tilgang av kontaktpersonen.
	 * 
	 * 
	 */
	public function hasBeenGrantedEditAccess($b_id = null) {
		if ($b_id != null) {
			$innslag = new innslag_v2($b_id);
		}
		else {
			$innslag = $this->innslag;
		}

		$userid = $this->current_user->getId();
		$repo = $this->container->get('doctrine')->getRepository("UKMDeltaBundle:GrantAccess");
		$request = $repo->findOneBy( array( 'ukmid' => $userid, 'requestBand' => $innslag->getId() ) );
		
		// Ingen request finnes
		if ( $request == null ) {
			return false;
		}

		if ( $request->getApproved() == true ) {
			return true;	
		}

		return false;
	}

	/** 
	 * Returnerer true dersom brukeren har bedt om å få tilgang.
	 *
	 */
	public function hasRequestedAccess($b_id = null) {
		if ($b_id != null) {
			$innslag = new innslag_v2($b_id);
		}
		else {
			$innslag = $this->innslag;
		}

		$userid = $this->current_user->getId();
		$repo = $this->container->get('doctrine')->getRepository("UKMDeltaBundle:GrantAccess");
		$request = $repo->findOneBy( array( 'ukmid' => $userid, 'requestBand' => $innslag->getId() ) );
		
		// Request finnes
		if ( $request != null ) {
			return true;
		}

		return false;
	}

	/** 
	 * Oppretter selve tilgangs-forespørselen i databasen, og gjør alle actions som hører med.
	 *
	 *
	 */
	public function requestAccess($b_id = null) {
		if ($b_id != null) {
			$innslag = new innslag_v2($b_id);
		}
		else {
			$innslag = $this->innslag;
		}

		if ( $this->hasRequestedAccess($innslag->getId() ) ) {
			// Kan ikke be om tilgang to ganger på samme innslag.
			return false; 
		}

		$userid = $this->current_user->getId();
		$repo = $this->container->get('doctrine')->getRepository("UKMDeltaBundle:GrantAccess");

		$request = new GrantAccess();
		$request->setUKMid( $this->current_user->getId() );
		$request->setRequestBand( $innslag->getId() );
		$request->setRequestTime( new DateTime("now") );

		// Save the request
		$em = $this->container->get('doctrine')->getEntityManager();
		$em->persist($request);
		$em->flush();
	}

	private function notifyContact($b_id = null) {
		if ($b_id != null) {
			$innslag = new innslag_v2($b_id);
		}
		else {
			$innslag = $this->innslag;
		}

		
	}
}
