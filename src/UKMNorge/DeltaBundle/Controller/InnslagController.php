<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
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

    public function whoAction($k_id, $pl_id, $type, $translationDomain)
    {
    	$view_data['k_id'] = $k_id;
    	$view_data['pl_id'] = $pl_id;
    	$view_data['type'] = $type;
    	$view_data['translationDomain'] = $translationDomain;

    	return $this->render('UKMDeltaBundle:Innslag:who.html.twig', $view_data );
    }

    public function removePersonAction($k_id, $pl_id, $b_id, $p_id) {
        $innslagService = $this->get('ukm_api.innslag');
        
        $innslagService->fjernPerson($b_id, $p_id);

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id));
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
            $p_id = $person->get('p_id');
            // Sett adresse og diverse.
            $personService->adresse($person, $user->getAddress(), $user->getPostNumber(), $user->getPostPlace(), $pl_id);
            // Sett alder basert på user-bundle-alder
            $alder = $user->getBirthdate();
            $personService->lagreAlder($p_id, $pl_id, $alder);
            // Oppdater personobjektet
            $person = $personService->hent($p_id);

            $user->setPameldUser($p_id);
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

    public function newTitleAction($k_id, $pl_id, $b_id) {

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');

        $view_data['innslag'] = $innslagService->hent($b_id);

        return $this->render('UKMDeltaBundle:Musikk:nyTittel.html.twig', $view_data);
    }

    public function editTitleAction($k_id, $pl_id, $b_id) {

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');

        // Tittel er et array bestående av:
        /*
            t_id
            b_id
            t_name
            t_titleby
            t_musicby
            t_coreography
            t_time
            season

            Der urelevante felt er tomme.
        */

        $view_data['tittel'] = null;
        $view_data['innslag'] = $innslagService->hent($b_id);

        return $this->render('UKMDeltaBundle:Musikk:nyTittel.html.twig', $view_data);
    }

    public function saveNewTitleAction($k_id, $pl_id, $b_id) {

        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);
        $request = Request::createFromGlobals();
        $seasonService = $this->get('ukm_delta.season');

        $tittelnavn = $request->request->get('tittel');
        $lengde = $request->request->get('lengde'); // I sekunder
        $season = $seasonService->getActive();

        if ($type == "musikk") {
            $sangtype = $request->request->get('sangtype');
            $selvlaget = $request->request->get('selvlaget');
            $tekstforfatter = $request->request->get('tekstforfatter');
            $melodiforfatter = $request->request->get('melodiforfatter');
        }
        elseif ($type == "dans") {
            $koreografi = $request->request->get('koreografi');
        }
  

        var_dump($tittelnavn);
        var_dump($lengde);
        var_dump($sangtype);
        var_dump($selvlaget);
        var_dump($tekstforfatter);
        var_dump($melodiforfatter);

        $form = array();

        if ($type == 'musikk') {
            $form['tittel'] = $tittelnavn;
            $form['tekst_av'] = $tittelnavn;
            $form['tittel'] = $tittelnavn;
        }  
        elseif ($type == 'dans') {
            $form['koreografi'] = $koreografi;
        }


        $tittel = new tittel(false); // Lag et tomt objekt
        $tittel->set('', $tittelnavn);
        // Send ting til innslag.class.php

        die();
        return $this->redirect('ukmid_delta_ukmid_pamelding_musikk_innslag', $view_data);
    }

    public function technicalAction($k_id, $pl_id, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);

        $innslagService = $this->get('ukm_api.innslag');
        $innslag = $innslagService->hent($b_id);

        $view_data['teknisk'] = $innslag->get('td_demand');
        return $this->render('UKMDeltaBundle:Innslag:teknisk.html.twig', $view_data);
    }

    public function saveTechnicalAction($k_id, $pl_id, $b_id) {
        $view_data = array( 'k_id' => $k_id, 'pl_id' => $pl_id, 'b_id' => $b_id);
        $innslagService = $this->get('ukm_api.innslag');
        $request = Request::createFromGlobals();

        $tekniskekrav = $request->request->get('teknisk');

        $innslagService->lagreTekniskeBehov($b_id, $tekniskekrav);

        return $this->redirectToRoute('ukmid_delta_ukmid_pamelding_musikk_innslag', $view_data);
    }
}