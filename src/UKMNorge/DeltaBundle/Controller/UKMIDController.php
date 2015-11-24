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

        $type = array();
        $pl_id = array();
        $innslagsliste2 = array();
        $frist = array();
        // List opp påmeldte og ikke fullførte innslag denne brukeren er kontaktperson for
        $contact_id = $user->getPameldUser();
        $innslagsliste = $innslagService->hentInnslagFraKontaktperson($contact_id, $user->getId());
        
        foreach ($innslagsliste as $innslag) {
            $pl_id[] = $innslag[1];
            
            $type[] = $innslag[2];
            $innslagsliste2[] = $innslag[0];
            $frist[] = $innslagService->sjekkFrist(false, $innslag[1]);
        }

        // var_dump($pl_id);
        // var_dump($type);
        // var_dump($innslagsliste2);
        
        $view_data['innslag'] = $innslagsliste2;
        $view_data['type'] = $type;
        $view_data['pl_id'] = $pl_id;
        $view_data['frist'] = $frist;
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

    public function editContactAction() {
        require_once('UKM/person.class.php');
        $user = $this->get('ukm_user')->getCurrentUser();
        $view_data = array();

        $view_data['translationDomain'] = 'ukmid';
        $view_data['user'] = $user;
        $person = new person($user->getPameldUser());
        $view_data['epost'] = $person->get('p_email');
        $view_data['age'] = $person->alder();
        return $this->render('UKMDeltaBundle:UKMID:contact.html.twig', $view_data);
    }

    public function saveContactAction() {
        require_once('UKM/person.class.php');
        $user = $this->get('ukm_user')->getCurrentUser();
        $person = new person($user->getPameldUser());
        $userManager = $this->container->get('fos_user.user_manager');
        
        // Ta i mot post-variabler
        $request = Request::createFromGlobals();

        $fornavn = $request->request->get('fornavn');
        $etternavn = $request->request->get('etternavn');
        $mobil = $request->request->get('mobil');
        $epost = $request->request->get('epost');
        $adresse = $request->request->get('adresse');
        $postnummer = $request->request->get('postnummer');
        $poststed = $request->request->get('poststed');
        $alder = $request->request->get('age');
        
        // Beregn birthdate basert på age?
        $birthYear = (int)date('Y') - $alder;
        $birthdate = mktime(0, 0, 0, 1, 1, $birthYear);
        $dato = new DateTime('now');
        $dato->setTimestamp($birthdate);

        // Lagre til UserBundle
        $user->setFirstName($fornavn);
        $user->setLastName($etternavn);
        $user->setPhone($mobil);
        $user->setEmail($epost);
        $user->setAddress($adresse);
        $user->setPostNumber($postnummer);
        $user->setPostPlace($poststed);
        $user->setBirthdate($dato);
        // Lagre user
        $userManager->updateUser($user);

        // Lagre til databasen
        $person->set('p_firstname', $fornavn);
        $person->set('p_lastname', $etternavn);
        $person->set('p_dob', $dato->getTimestamp());
        $person->set('p_email', $epost);
        $person->set('p_phone', $mobil);
        #$person->set('p_kommune', '');
        $person->set('p_postnumber', $postnummer);
        $person->set('p_postplace', $poststed);
        $person->set('p_adress', $adresse);

        $person->lagre('delta', $user->getPameldUser());
        // Legg til info om det gikk bra
        $this->addFlash('success', 'Endringene ble lagret!');
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }
}
