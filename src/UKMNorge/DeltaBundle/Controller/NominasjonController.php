<?php
namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Exception;

use innslag_v2;
use nominasjon;
use write_nominasjon;
use nominasjon_media;
use write_nominasjon_media;
use monstringer_v2;

use UKMlogger;

class NominasjonController extends Controller
{
	/**
	 * VelgAction
	 *
	 * Brukeren velger media eller arrangør
	**/
	public function velgAction() {
		$view_data = array(
			'translationDomain' => 'nominasjon'
		);
		return $this->render('UKMDeltaBundle:Nominasjon:velg.html.twig', $view_data );
	}

	/**
	 * ArrangørInfoAction
	 *
	 * Vis brukeren info om hva nominasjon og arrangør er.
	 * Brukeren må ta stilling til om h*n kan delta på både planleggingshelg og festival
	**/
	public function arrangorInfoAction() {
		$view_data = [
			'translationDomain' => 'nominasjon',
		];
		return $this->render('UKMDeltaBundle:Nominasjon:arrangor.html.twig', $view_data );
	}
	
	/**
	 * Sjekk at deltakeren kan være med på både planleggingshelg og festival
	 *
	 * NO: render: sorry
	 * YES: redirectTo veivalg
	**/
	public function arrangorInfoSaveAction( Request $request ) {
		$view_data = [
			'translationDomain' => 'nominasjon',
		];

		$planhelg = $request->request->get('planhelg');
		$festival = $request->request->get('festival');

		if( $planhelg == 'ja' && $festival == 'ja' ) {
			return $this->redirectToRoute('ukm_nominasjon_arrangor_veivalg');
		}
		
		return $this->render('UKMDeltaBundle:Nominasjon:sorry.html.twig', $view_data);
	}
	
	/**
	 * ArrangørVeivalgAction
	 *
	 *
	**/
	public function arrangorVeivalgAction(){
		$view_data = [
			'translationDomain' => 'nominasjon',
		];

		return $this->render('UKMDeltaBundle:Nominasjon:arrangor_veivalg.html.twig', $view_data);
	}
	
	public function arrangorVeivalgSaveAction( Request $request ) {
		$lydtekniker = $request->request->get('lydtekniker') == 'true';
		$lystekniker = $request->request->get('lystekniker') == 'true';
		$vertskap = $request->request->get('vertskap') == 'true';
		
		$step = [];
		if( $lydtekniker ) {
			$step[] = 'lydtekniker';
		}
		if( $lystekniker ) {
			$step[] = 'lystekniker';
		}
		if( $vertskap ) {
			// Har ikke et eget vertskap-step foreløpig
			#$step[] = 'vertskap';
		}
		
		if( sizeof( $step ) == 0 ) {
			$this->get('session')->getFlashBag()->set('danger', 'Du må minst velge én av de tre kategoriene.');
			return $this->redirectToRoute('ukm_nominasjon_arrangor_veivalg');
		}
		
		$this->get('session')->set('nominasjon_arrangor_step', $step);
		
		$nominasjon = $this->_loadOrCreateNominasjon( 'arrangor' );
		$nominasjon->setLydtekniker( $lydtekniker );
		$nominasjon->setLystekniker( $lystekniker );
		$nominasjon->setVertskap( $vertskap );
		
		$nominasjon->setSamarbeid( $request->request->get('samarbeid') );
		$nominasjon->setErfaring( $request->request->get('erfaring') );
		$nominasjon->setSuksesskriterie( $request->request->get('suksesskriterie') );
		$nominasjon->setAnnet( $request->request->get('annet') );
		
		write_nominasjon::saveArrangor( $nominasjon );
		
		return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer');
	}
	
	public function arrangorDetaljerAction( $type ) {
		$view_data = [
			'translationDomain' => 'nominasjon',
		];

		switch( $type ) {
			case 'lydtekniker':
			case 'lystekniker':
				return $this->render('UKMDeltaBundle:Nominasjon:arrangor_'. $type .'.html.twig', $view_data);

			default:
				$steps = $this->get('session')->get('nominasjon_arrangor_step');
				
				if( is_array( $steps ) && sizeof( $steps ) > 0 ) {
					$next = array_shift( $steps );
					$this->get('session')->set('nominasjon_arrangor_step', $steps);
					
					return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer', ['type' => $next] );
				} else {
					$this->get('session')->getFlashBag()->set('success', 'Takk! Vi har nå tatt i mot ditt nominasjonsskjema.');
					
					return $this->redirectToRoute('ukm_delta_ukmid_homepage');
				}
		}
	}
	
	public function arrangorDetaljerSaveAction( Request $request, $type ) {
		$nominasjon = $this->_loadOrCreateNominasjon( 'arrangor' );

		switch( $type ) {
			case 'lydtekniker':
				for( $i=1; $i<=6; $i++ ) {
					$funksjon = 'setLydErfaring'. $i;
					$nominasjon->{$funksjon}( $request->request->get('lyd-erfaring-'.$i) );
				}
				break;
			case 'lystekniker':
				for( $i=1; $i<=6; $i++ ) {
					$funksjon = 'setLysErfaring'. $i;
					$nominasjon->{$funksjon}( $request->request->get('lys-erfaring-'.$i) );
				}
				break;
			default: 
				throw new Exception('Beklager, prøvde å lagre arrangør-erfaring av ukjent type, og det går ikke.');
		}
		
		write_nominasjon::saveArrangor( $nominasjon );
		return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer');
	}

	public function mediaAction() {
		$omrader = [
			'tekst' => 'Tekst',
			'foto' => 'Foto',
			'film' => 'Film',
			'snap' => 'Snapchat',
			'flerkamera-regi' => 'Flerkamera, regi',
			'flerkamera-kamera' => 'Flerkamera, kameraoperatør',
			'programmering' => 'Programmering (HTML/JS/CSS/PHP)',
#			'annet' => 'Er det noe annet du kan, som du vil gjøre?',
		];
		
		$view_data = [
			'translationDomain' => 'nominasjon',
			'omrader' => $omrader,
		];
		return $this->render('UKMDeltaBundle:Nominasjon:media.html.twig', $view_data );
	}
	
	public function mediaSaveAction( Request $request ) {

		$nominasjon = $this->_loadOrCreateNominasjon( 'media' );
		$nominasjon->setPri1( $request->request->get('pri-1') );
		$nominasjon->setPri2( $request->request->get('pri-2') );
		$nominasjon->setPri3( $request->request->get('pri-3') );
		$nominasjon->setAnnet( $request->request->get('annet') );
		$nominasjon->setBeskrivelse( $request->request->get('beskrivelse') );
		write_nominasjon::saveMedia( $nominasjon );
		
		$this->get('session')->getFlashBag()->set('success', 'Takk! Vi har nå tatt i mot ditt nominasjonsskjema.');
		
		return $this->redirectToRoute('ukm_delta_ukmid_homepage');
	}
	
	private function _loadOrCreateNominasjon( $type ) {
		require_once('UKM/logger.class.php');
		require_once('UKM/innslag.class.php');
		require_once('UKM/monstringer.class.php');
		require_once('UKM/nominasjon_media.class.php');
		require_once('UKM/write_nominasjon.class.php');

		$user = $this->get('ukm_user')->getCurrentUser();
		
		$innslagService = $this->get('ukm_api.innslag');
		$innslagsliste = $innslagService->hentInnslagFraKontaktperson($user->getPameldUser(), $user->getId());

		$nominert = false;
		$type_compare = $type == 'media' ? 'nettredaksjon' : $type; // korriger for nettredaksjon/media
		foreach( $innslagsliste['fullstendig'] as $container ) {
			$loop_innslag = new innslag_v2( $container->innslag->id );
			if( $loop_innslag->getType()->getKey() == $type_compare && $loop_innslag->erVideresendt() ) {
				$nominert = $loop_innslag;
			}
		}
		
		if( !$nominert ) {
			throw new Exception('Beklager, akkurat nå krever systemet at du har en påmelding på fylkesfestivalen før du kan nomineres. Vi jobber med å rette problemet, men send en epost til support@ukm.no om at du sliter med nominasjonen, så skal vi hjelpe deg' );
		}
		
		$fylke_monstring = monstringer_v2::fylke( $nominert->getKommune()->getFylke(), $this->get('ukm_delta.season')->getActive() );
		
		UKMlogger::setID( 'delta', $user->getPameldUser(), $fylke_monstring->getId() );
		
		// CREATE OR SELECT EXISTING
		$nominasjon = write_nominasjon::create( 
			$nominert->getId(),								// Innslag ID
			$this->get('ukm_delta.season')->getActive(), 	// Sesong
			'land', 										// TODOondemand: støtt også nominasjon fra lokal til fylke
			$nominert->getKommune(), 						// Innslagets kommune
			$type											// Type nominasjon
		);
		return $nominasjon;
	}
}
?>