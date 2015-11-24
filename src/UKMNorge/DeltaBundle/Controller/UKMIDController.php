<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;

class UKMIDController extends Controller
{
    public function indexAction()
    {
        require_once("UKM/innslag.class.php");
	    $view_data = array();
        $view_data['translationDomain'] = 'ukmid';
	    $user = $this->get('ukm_user')->getCurrentUser();
	    $view_data['user'] = $user;
        $innslagService = $this->get('ukm_api.innslag');
        $season = $this->get('ukm_delta.season')->getActive();

        $innslagsliste = array();

        // List opp påmeldte og ikke fullførte innslag denne brukeren er kontaktperson for
        $contact_id = $user->getPameldUser();
        $innslagsliste = $innslagService->hentInnslagFraKontaktperson($contact_id, $user->getId());
        
		// Sjekk opp frist for alle innslagene
		foreach( $innslagsliste as $gruppe => $alle_innslag ) {
			foreach( $alle_innslag as $innslag ) {
		        $innslag->monstring = $innslag->innslag->min_lokalmonstring( $season );
		        $innslag->tittellos = $innslag->innslag->tittellos();
		        $innslag->pamelding_apen = $innslag->monstring->subscribable( 'pl_deadline'. ($innslag->tittellos ? '2':'') );
	        }
        }
        $view_data['alle_innslag'] = $innslagsliste;
        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }

    public function checkInfoAction()
    {
        $view_data = array();
        $view_data['translationDomain'] = 'ukmid';

        $user = $this->get('ukm_user')->getCurrentUser();
        
        // Har vi all data lagret om denne brukeren?
        if ($user->getAddress() != null && $user->getPostNumber() != null && $user->getPostPlace() != null && $user->getBirthdate() != null) {
            // Gå videre til geovalg
            return $this->redirectToRoute('ukm_delta_ukmid_pamelding');
        }

        // Beregn alder fra fødselsår
        if ($birthdate = $user->getBirthdate()) {
            $now = new DateTime('now');
            $age = $birthdate->diff($now)->y;
            $view_data['age'] = $age;
        }
        
        $view_data['user'] = $user;
        //var_dump($view_data['user']);
        // Legg til data som vi allerede har lagret, i tilfelle valideringsfeil

        // Rendre fyll-inn-visningen.
        return $this->render('UKMDeltaBundle:UKMID:info.html.twig', $view_data );
    }

    public function verifyInfoAction()
    {
        $dato = new DateTime('now');
        $userManager = $this->container->get('fos_user.user_manager');

        $user = $this->get('ukm_user')->getCurrentUser();

        // Ta imot post-variabler
        $request = Request::createFromGlobals();

        $address = $request->request->get('address');
        $postNumber = $request->request->get('postNumber');
        $postPlace = $request->request->get('postplace');
        $age = $request->request->get('age');

        //TODO: Sikkerhetssjekk input?

        // Beregn birthdate basert på age?
        $birthYear = (int)date('Y') - $age;

        $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
        $dato->setTimestamp($birthdate);
        // Legg til verdier i user-bundle
        $user->setAddress($address);
        $user->setPostNumber($postNumber);
        $user->setPostPlace($postPlace);
        $user->setBirthdate($dato);

        $userManager->updateUser($user);
        // Alt lagret ok
        return $this->redirectToRoute('ukm_delta_ukmid_checkinfo');
    }

}
