<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use person;

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
        $view_data['dinside'] = true;
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

        // Beregn birthdate basert på alder
        if ($age != 0) {
            $birthYear = (int)date('Y') - $age;
            $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
            $dato->setTimestamp($birthdate);
        }
        else {
            // Tilsvarer UNIX Timestamp = 0. Kunne også lagra som en int.
            $dato->setTimestamp(0);
        }
        // Legg til verdier i user-bundle
        $user->setAddress($address);
        $user->setPostNumber($postNumber);
        $user->setPostPlace($postPlace);
        $user->setBirthdate($dato);

        $userManager->updateUser($user);
        // Alt lagret ok
        return $this->redirectToRoute('ukm_delta_ukmid_checkinfo');
    }

    public function editContactAction() {
        $view_data = array();
        require_once('UKM/person.class.php');
        $personService = $this->get('ukm_api.person');
        $user = $this->get('ukm_user')->getCurrentUser();

        if ($user->getPameldUser() == null) {
           
            // Hent alder fra UserBundle
            $view_data['epost'] = $user->getEmail();
            //$view_data['age'] = null;
        }
        else {
            $person = $personService->hent($user->getPameldUser());
            $view_data['person'] = $person; 
            $view_data['age'] = $personService->alder($person);
            if ($view_data['age'] == '25+') {
                $view_data['age'] = 0;
            }
            $view_data['epost'] = $person->get('p_email');
        }
        
       
        $view_data['translationDomain'] = 'ukmid';
        $view_data['user'] = $user;        
        // $person = new person($user->getPameldUser());
        return $this->render('UKMDeltaBundle:UKMID:contact.html.twig', $view_data);
    }

    public function saveContactAction() {
        require_once('UKM/person.class.php');
        $user = $this->get('ukm_user')->getCurrentUser();
        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->container->get('ukm_api.innslag');
        
        // Ta i mot post-variabler
        $request = Request::createFromGlobals();

        // Dette vet vi alltid
        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $mobil = $request->request->get('mobil');
        $epost = $request->request->get('epost');

        // Dette vet vi kun om personen har meldt på et innslag!
        if ($user->getPameldUser() != null) {
            $person = new person($user->getPameldUser());
            // Alder
            $alder = $request->request->get('age');
            // Beregn birthdate basert på age?
            if ($alder != 0) {
                $birthYear = (int)date('Y') - $alder;
            }
            else {
                $birthYear = 1970;
            }
            $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
            $dato = new DateTime('now');
            $dato->setTimestamp($birthdate);
            $user->setBirthdate($dato);
            $person->set('p_dob', $dato->getTimestamp());
            
            // Adresse
            $adresse = $request->request->get('adresse');
            $user->setAddress($adresse);
            $person->set('p_adress', $adresse);

            $postnummer = $request->request->get('postnummer');
            $user->setPostNumber($postnummer); 
            $person->set('p_postnumber', $postnummer);
            
            // Poststed
            $poststed = $request->request->get('poststed'); 
            $user->setPostPlace($poststed);
            $person->set('p_postplace', $poststed);

            // Lagre til databasen
            $person->set('p_firstname', $fornavn);
            $person->set('p_lastname', $etternavn);
            $person->set('p_email', $epost);
            $person->set('p_phone', $mobil);
            
            $person->lagre('delta', $user->getPameldUser());

            // Har personen et eller flere tittelløse innslag?
            // I så fall, oppdater navn på disse
            $innslagsliste = $innslagService->hentInnslagFraKontaktperson($user->getPameldUser(), $user->getId());
            //var_dump($innslagsliste);
            foreach ($innslagsliste['fullstendig'] as $innslag) {
                if ($innslag->innslag->tittellos()) {
                    // Innslaget er et tittelløst innslag
                    $innslagService->lagreArtistnavn($innslag->innslag->get('b_id'), $fornavn . ' '. $etternavn);
                }
            }
            foreach ($innslagsliste['ufullstendig'] as $innslag) {
                if ($innslag->innslag->tittellos()) {
                    // Innslaget er et tittelløst innslag
                    $innslagService->lagreArtistnavn($innslag->innslag->get('b_id'), $fornavn . ' '. $etternavn);
                }
            }
        }
        

        
        // Lagre til UserBundle
        $user->setFirstName($fornavn);
        $user->setLastName($etternavn);
        $user->setPhone($mobil);
        $user->setEmail($epost);
    
        // Lagre user
        $userManager->updateUser($user);

        // Legg til info om det gikk bra
        $this->addFlash('success', 'Endringene ble lagret!');
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }

    public function supportAction() {
        $view_data['translationDomain'] = 'ukmid';
        return $this->render('UKMDeltaBundle:UKMID:support.html.twig', $view_data);
    }
}
