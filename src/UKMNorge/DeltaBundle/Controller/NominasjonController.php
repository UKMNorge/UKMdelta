<?php
namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use UKMNorge\UserBundle\Services\UserService;
use UKMNorge\APIBundle\Services\InnslagService;
use UKMNorge\APIBundle\Services\SessionService;
use Symfony\Component\HttpFoundation\Session\Session;
use Exception;

use UKMNorge\Innslag\Nominasjon\Write as NominasjonWrite;
use UKMNorge\Log\Logger;
use UKMmail;

class NominasjonController extends Controller
{
	/**
	 * VelgAction
	 *
	 * Brukeren velger nominert innslag.
	**/
	public function velgAction() {
        $innslagService = new InnslagService($this->container);

		$alle_innslag = $innslagService->hentInnslagFraKontaktperson()->getAll();
		$nominerte_innslag = array();
		foreach($alle_innslag as $innslag) {
			if ( $innslag->getNominasjoner()->getAntall() > 0 ) {
				$nominerte_innslag[] = $innslag;
			}
		}

		// Ved kun ett skjema å fylle ut, kan vi sende brukeren rett dit så lenge fristen ikke er gått ut og brukeren ikke har fylt det ut enda.
		if( count($nominerte_innslag) == 1 && $nominerte_innslag[0]->getNominasjoner()->getAntall() == 1 ) {
			$innslag = $nominerte_innslag[0];
			$nominasjon = $innslag->getNominasjoner()->getAll()[0];

			if(
				date("now") < $nominasjon->getTilArrangement()->getFrist(1) &&
				!$nominasjon->harDeltakerSkjema() ) 
			{
				return $this->redirectToRoute( 'ukm_nominasjon_finn_skjema', ['id' => $nominasjon->getId()] );
			}
		}

		$view_data = array(
			'translationDomain' => 'nominasjon',
			'nominerte_innslag' => $nominerte_innslag			
		);

		return $this->render('UKMDeltaBundle:Nominasjon:velg.html.twig', $view_data );
	}

	/**
	 * Hent nominasjonen fra id, og sjekk at den tilhører denne brukeren.
	 *
	 *
	 */
	private function getNominasjon( $nominasjons_id ) {
        $innslagService = new InnslagService($this->container);
		// Verifiser at nominasjonen tilhører denne brukeren.
		$alle_innslag = $innslagService->hentInnslagFraKontaktperson()->getAll();

		$nominert_innslag = null;
		$nominasjon = null;
		foreach($alle_innslag as $innslag) {
			foreach ( $innslag->getNominasjoner()->getAll() as $nominasjon_i ) {
				if( $nominasjon_i->getId() == $nominasjons_id) {
					$nominasjon = $nominasjon_i;
				}
			}
		}

		if( null == $nominasjon) {
			throw new Exception("Fant ikke denne nominasjonen!");
		}

		return $nominasjon;
	}

	/**
	 * Brukeren har valgt hvilken nominasjon han vil fylle ut skjema for. 
	 *
	 */
	public function finnSkjemaAction(Request $request, int $id) {
		$nominasjon = $this->getNominasjon($id);

		$session = $this->getSession();

		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $nominasjon
		];

		// Velg skjmea ut fra nominasjons type og destinasjon
		if($nominasjon->getTilArrangement()->getType() == 'land') {
			if($nominasjon->getType() == 'arrangor') {
				// Redirect til ekstraordinær info for festival-deltakerne
				return $this->redirectToRoute('ukm_nominasjon_arrangor');
			} 
		}

		// Arrangører og media på andre enn landsfestivalen behandles likt.
		if($nominasjon->getType() == 'arrangor') 
		{
			if( is_array( $session->get('form-data') ) ) {
				$view_data = array_merge( $view_data, $session->get('form-data') );
				$session->remove('form-data');
			}

			return $this->render('UKMDeltaBundle:Nominasjon:arrangor_veivalg.html.twig', $view_data);
		}
		elseif ($nominasjon->getType() == 'nettredaksjon' || $nominasjon->getType() == 'media') 
		{	
			$omrader = [
			'tekst' => 'Tekst',
			'foto' => 'Foto',
			'film' => 'Film',
			'flerkamera-regi' => 'Flerkamera, regi',
			'flerkamera-kamera' => 'Flerkamera, kameraoperatør',
			'design' => 'Design',
			'some' => 'Sosiale medier (instagram og facebook)',
#			'programmering' => 'Programmering (HTML/JS/CSS/PHP)'
#			'annet' => 'Er det noe annet du kan, som du vil gjøre?',
		];
		
		$view_data['omrader'] = $omrader;
		return $this->render('UKMDeltaBundle:Nominasjon:media.html.twig', $view_data );
		} else {
			throw new Exception("Vi fant ikke en nominasjon for denne typen påmelding.");
		}
	}

	/**
	 * ArrangørInfoAction - kun for festivalen
	 *
	 * Vis brukeren info om hva nominasjon og arrangør er.
	 * Brukeren må ta stilling til om h*n kan delta på både planleggingshelg og festival
	**/
	public function arrangorInfoAction(Request $request, $id) {
		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $this->getNominasjon($id)
		];
		return $this->render('UKMDeltaBundle:Nominasjon:arrangor.html.twig', $view_data );
	}
	
	/**
	 * Sjekk at deltakeren kan være med på både planleggingshelg og festival
	 *
	 * NO: render: sorry
	 * YES: redirectTo veivalg
	**/
	public function arrangorInfoSaveAction( Request $request, $id ) {
		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $this->getNominasjon($id)
		];
		
		$nominasjon = $this->getNominasjon($id);

		$planhelg = $request->request->get('planhelg');
		$festival = $request->request->get('festival');

		if( $planhelg == 'ja' && $festival == 'ja' ) {
			// Send videre i prosessen
			return $this->redirectToRoute('ukm_nominasjon_arrangor_veivalg', ['id' => $id]);
		}
		
		/**
		 * Lagrer et flagg i sorry-feltet som sier hva brukeren har svart nei til
		 * Nullstilles ikke av fullstendig nominasjon, men vises da som advarsel til arrangør
		**/
		if( $planhelg != 'ja' && $festival != 'ja' ) {
			$flagg = 'begge';
		} elseif( $planhelg != 'ja' ) {
			$flagg = 'planleggingshelg';
		} else {
			$flagg = 'festivalen';
		}
		
		NominasjonWrite::saveSorry( $nominasjon, $flagg);
		return $this->render('UKMDeltaBundle:Nominasjon:sorry.html.twig', $view_data);
	}
	
	/**
	 * ArrangørVeivalgAction
	 *
	 *
	**/
	public function arrangorVeivalgAction(Request $request, $id){
		$session = $this->getSession();

		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $this->getNominasjon($id)
		];
		
		if( is_array( $session->get('form-data') ) ) {
			$view_data = array_merge( $view_data, $session->get('form-data') );
			$session->remove('form-data');
		}

		return $this->render('UKMDeltaBundle:Nominasjon:arrangor_veivalg.html.twig', $view_data);
	}
	
	public function arrangorVeivalgSaveAction( Request $request, $id ) {
		$userObj = new UserService($this->container);
		$session = $this->getSession();

		
		$user = $userObj->getCurrentUser();

		$lydtekniker = $request->request->get('lydtekniker') == 'true';
		$lystekniker = $request->request->get('lystekniker') == 'true';
		$vertskap = $request->request->get('vertskap') == 'true';
		$produsent = $request->request->get('produsent') == 'true';
		
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
		
		if( !$lydtekniker && !$lystekniker && !$vertskap ) {
			$data = [
				'form_samarbeid'	=> $request->request->get('samarbeid'),
				'form_erfaring' 	=> $request->request->get('erfaring'),
				'form_suksess'		=> $request->request->get('suksesskriterie'),
				'form_annet'		=> $request->request->get('annet'),
			];
			$session->set('form-data', $data);
			$session->getFlashBag()->set('danger', 'Du må minst velge én av de fire kategoriene.');
			return $this->redirectToRoute('ukm_nominasjon_arrangor_veivalg');
		}
		
		$session->set('nominasjon_arrangor_step', $step);
		
		$nominasjon = $this->getNominasjon($id);

		$nominasjon->setLydtekniker( $lydtekniker );
		$nominasjon->setLystekniker( $lystekniker );
		$nominasjon->setProdusent( $produsent );
		$nominasjon->setVertskap( $vertskap );
		
		$nominasjon->setSamarbeid( $request->request->get('samarbeid') );
		$nominasjon->setErfaring( $request->request->get('erfaring') );
		$nominasjon->setSuksesskriterie( $request->request->get('suksesskriterie') );
		$nominasjon->setAnnet( $request->request->get('annet') );
		
		Logger::setID('delta', $user->getId(), $nominasjon->getTilArrangement()->getId());
		NominasjonWrite::saveArrangor( $nominasjon );
		
		return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer', ['id' => $id]);
	}
	
	public function arrangorDetaljerAction( $type, $id ) {
		$session = $this->getSession();

		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $this->getNominasjon($id)
		];

		switch( $type ) {
			case 'lydtekniker':
			case 'lystekniker':
				return $this->render('UKMDeltaBundle:Nominasjon:arrangor_'. $type .'.html.twig', $view_data);

			default:
				$steps = $session->get('nominasjon_arrangor_step');
				
				if( is_array( $steps ) && sizeof( $steps ) > 0 ) {
					$next = array_shift( $steps );
					$session->set('nominasjon_arrangor_step', $steps);
					
					return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer', ['id' => $id, 'type' => $next] );
				} else {
					$session->getFlashBag()->set('success', 'Takk! Vi har nå tatt i mot ditt nominasjonsskjema.');
					
					return $this->redirectToRoute('ukm_delta_ukmid_homepage');
				}
		}
	}
	
	public function arrangorDetaljerSaveAction( Request $request, $id, $type ) {
		$userObj = new UserService($this->container);
		
		$user = $userObj->getCurrentUser();
		$nominasjon = $this->getNominasjon($id);

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

		Logger::setID('delta', $user->getId(), $nominasjon->getTilArrangement()->getId());
		NominasjonWrite::saveArrangor( $nominasjon );
		return $this->redirectToRoute('ukm_nominasjon_arrangor_detaljer', ['id' => $id]);
	}

	public function mediaAction(Request $request, $id) {
		$omrader = [
			'tekst' => 'Tekst',
			'foto' => 'Foto',
			'film' => 'Film',
			'flerkamera-regi' => 'Flerkamera, regi',
			'flerkamera-kamera' => 'Flerkamera, kameraoperatør',
			'design' => 'Design',
			'some' => 'Sosiale medier (instagram og facebook)',
			'programmering' => 'Programmering (HTML/JS/CSS/PHP)'
#			'annet' => 'Er det noe annet du kan, som du vil gjøre?',
		];
		
		$view_data = [
			'translationDomain' => 'nominasjon',
			'nominasjon' => $this->getNominasjon($id),
			'omrader' => $omrader,
		];
		return $this->render('UKMDeltaBundle:Nominasjon:media.html.twig', $view_data );
	}

	public function mediaSaveAction( Request $request, $id ) {
		$session = $this->getSession();

		$nominasjon = $this->getNominasjon($id);
		$userObj = new UserService($this->container);
		
		$user = $userObj->getCurrentUser();

		$nominasjon->setPri1( $request->request->get('pri-1') );
		$nominasjon->setPri2( $request->request->get('pri-2') );
		$nominasjon->setPri3( $request->request->get('pri-3') );
		$nominasjon->setAnnet( $request->request->get('annet') );
		$nominasjon->setBeskrivelse( $request->request->get('beskrivelse') );

		Logger::setID('delta', $user->getId(), $nominasjon->getTilArrangement()->getId());
		NominasjonWrite::saveMedia( $nominasjon );
		
		$session->getFlashBag()->set('success', 'Takk! Vi har nå tatt i mot ditt nominasjonsskjema.');
		
		return $this->redirectToRoute('ukm_delta_ukmid_homepage');
	}
	
	private function _sendMissingUserEmail() {
		require_once('UKM/mail.class.php');
		$userObj = new UserService($this->container);
		$user = $userObj->getCurrentUser();

		$epost = new UKMmail();
		$epost->text(
					$user->getName() .
					' (PID: '. $user->getPameldUser() .') ' .
					' får ikke fullført nominasjonen sin, da systemet ikke finner en videresendt bruker. Fint om dette kan fikses ASAP.'
			)
			->to('support@ukm.no')
			->subject( 'NOMINASJON-FEIL: '. $user->getName() )
			->setReplyTo( $user->getEmail(), $user->getName() )
			->setFrom( $user->getEmail(), $user->getName() )
		  ->ok();

		return $this->render('UKMDeltaBundle:Nominasjon:ingenbruker.html.twig', [] );
	}

	private function getSession() : Session {
        $session = SessionService::getSession();
        return $session;
    }
}
?>