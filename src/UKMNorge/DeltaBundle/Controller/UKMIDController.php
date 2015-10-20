<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use monstring;
use monstringer;

class UKMIDController extends Controller
{
    public function indexAction()
    {
	    $view_data = array();
	    
	    $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        return $this->render('UKMDeltaBundle:UKMID:index.html.twig', $view_data );
    }

    public function pameldingAction()
    {
    	$view_data = array();

    	$view_data['user'] = $this->get('ukm_user')->getCurrentUser();
    	return $this->render('UKMDeltaBundle:UKMID:pamelding.html.twig', $view_data);
    }

    public function geoAction()
    {
        require_once('UKM/monstringer.class.php');

        $season = $this->container->get('ukm_delta.season')->getActive();

        $monstringer = new monstringer($season);
        $liste = $monstringer->alle_kommuner_med_lokalmonstringer();

        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();
        $view_data['monstringsliste'] = $liste;

        //var_dump($liste[1]);
        return $this->render('UKMDeltaBundle:UKMID:geo.html.twig', $view_data);
    }

    public function typeAction($k_id, $pl_id)
    {
        require_once('UKM/monstring.class.php');
        // Hent lister om hvilke typer som er tillatt på denne mønstringen.
        $pl = new monstring($pl_id);
        $typeListe = $pl->getAllBandTypesDetailedNew();
        //var_dump($typeListe);
        $view_data['typer'] = $typeListe;
        $view_data['pl_id'] = $pl_id;

        $view_data['user'] = $this->get('ukm_user')->getCurrentUser();

        return $this->render('UKMDeltaBundle:UKMID:type.html.twig', $view_data);
    }
}
