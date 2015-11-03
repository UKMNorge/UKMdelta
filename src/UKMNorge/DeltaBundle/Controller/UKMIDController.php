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
	    $user = $this->get('ukm_user')->getCurrentUser();
	    $view_data['user'] = $user;
        $innslagService = $this->get('ukm_api.innslag');
        // List opp påmeldte og ikke fullførte innslag denne brukeren er kontaktperson for
        $contact_id = $user->getPameldUser();
        $innslagsliste = $innslagService->hentInnslagFraKontaktperson($contact_id, $user->getId());
        $innslagsliste2 = array();
        foreach ($innslagsliste as $innslag) {
            $pl_id[] = $innslag[1];
            $type[] = $innslag[2];
            $innslagsliste2[] = $innslag[0];
        }
        // var_dump($pl_id);
        // var_dump($type);
        // var_dump($innslagsliste2);
        
        $view_data['innslag'] = $innslagsliste2;
        $view_data['type'] = $type;
        $view_data['pl_id'] = $pl_id;
        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }

    public function checkInfoAction()
    {

        $view_data = array();
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
