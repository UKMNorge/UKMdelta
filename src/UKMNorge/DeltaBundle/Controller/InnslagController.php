<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use monstring;
use monstringer;
use innslag;
use Exception;

class InnslagController extends Controller
{
    public function pameldingAction()
    {
    	$view_data = array();

    	$view_data['user'] = $this->get('ukm_user')->getCurrentUser();
    	return $this->render('UKMDeltaBundle:Innslag:pamelding.html.twig', $view_data);
    }

    public function geoAction()
    {
        require_once('UKM/monstringer.class.php');

        $season = $this->container->get('ukm_delta.season')->getActive();

        $monstringer = new monstringer($season);
        $liste = $monstringer->alle_kommuner_med_lokalmonstringer();

        //var_dump($liste);
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['monstringsliste'] = $liste;

        //var_dump($liste[1]);
        return $this->render('UKMDeltaBundle:Innslag:geo.html.twig', $view_data);
    }

    public function typeAction($k_id, $pl_id)
    {
        require_once('UKM/monstring.class.php');
        // Hent lister om hvilke typer som er tillatt på denne mønstringen.
        $pl = new monstring($pl_id);
        $typeListe = $pl->getAllBandTypesDetailedNew();
        //var_dump($typeListe);
        $view_data['k_id'] = $k_id;
        $view_data['pl_id'] = $pl_id;
        $view_data['typer'] = $typeListe;
    
        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        return $this->render('UKMDeltaBundle:Innslag:type.html.twig', $view_data);
    }

    public function whoAction($k_id, $pl_id, $type, $knapp1, $knapp2)
    {
    	$view_data['k_id'] = $k_id;
    	$view_data['pl_id'] = $pl_id;
    	$view_data['type'] = $type;
    	$view_data['knapp1'] = $knapp1;
    	$view_data['knapp2'] = $knapp2;

    	return $this->render('UKMDeltaBundle:Innslag:who.html.twig', $view_data );
    }

    public function createAction($k_id, $pl_id, $type, $hvem) 
    {
    	require_once('UKM/innslag.class.php');

    	$user = $this->get('ukm_user')->getCurrentUser();
        $userManager = $this->container->get('fos_user.user_manager');
        $innslagService = $this->get('ukm_api.innslag');
        $personService = $this->get('ukm_api.person');

        // Hvis brukeren ikke er registrert i systemet fra før
        if ($user->getPameldUser() === null) {
            // Create user
            $person = $personService->opprett($user->getFirstname(), $user->getLastname(), $user->getPhone(), $pl_id);
            // Sett adresse og diverse.
            $personService->adresse($person, $user->getAddress(), $user->getPostNumber(), $user->getPostPlace());
            // Oppdater personobjektet
            $person = $personService->hent($person->get('p_id'));
            $user->setPameldUser($person->get('p_id'));
            // Oppdater verdier i UserBundle
            $userManager->updateUser($user);
        }
        else {
            // Hent brukerobjektet dersom det finnes
            $person = $personService->hent($user->getPameldUser());
        }

        // Opprett et nytt innslag
        $innslag = $innslagService->opprett($k_id, $pl_id, $type, $hvem, $person, $user->getId());        

        //var_dump($hvem);

    	// var_dump($user);
    	// var_dump($person);
    	//var_dump($innslag);
    	return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $innslag->get('b_id')));
    }
}