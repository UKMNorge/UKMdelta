<?php
# SjekkController.php
#

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Exception;

use landsmonstring;
use UKMNorge\Database\SQL\Insert;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Innslag\Innslag;
use UKMNorge\Innslag\Personer\Person;

require_once('UKM/Autoloader.php');

class SjekkController extends Controller {

	public function indexAction($mobile, $hash) {
		$view_data = array();

		$sql = new Query("SELECT * FROM `ukm_sjekk` WHERE `phone` = '#mobile'", array('mobile' => $mobile));
		#echo 'Debug:<br>';
		#echo $sql->debug();
		#$sql->error(); // Turn on errors
		$res = $sql->getArray();
		#echo '<br>mysql-ping: '.(mysql_ping() ? 'true' : 'false');
		#echo '<br>$res: '; 
		#var_dump($res); 
		#echo '<br>$registered_hash: ';
		$registered_hash = $res['hash'];
		#var_dump($registered_hash); 
		#echo ' != ';
		#var_dump($hash);
		if ($registered_hash != $hash) {
			return $this->render('UKMDeltaBundle:Sjekk:notallowed.html.twig', $view_data);
		}

		$season = $this->container->get('ukm_delta.season')->getActive();
		$monstring_handle = new landsmonstring($season);
		$landsmonstring = $monstring_handle->monstring_get();

		$m_innslag = $landsmonstring->innslag();
		$m_innslag_id = array();
		foreach($m_innslag as $innslag) {
			$m_innslag_id[] = $innslag['b_id'];
		}
		
		$view_data['pl_id'] = $landsmonstring->info['pl_id'];

		# Personer med dette mobilnummeret:
		$qry = new Query("SELECT * FROM `smartukm_participant`
						WHERE `p_phone` = '#mobile'", array('mobile' => $mobile)
					);
		$res = $qry->run();
		$persons = array();
		if(!$res) {
			throw new Exception('Systemfeil: Noe gikk feil i tilkoblingen til databasen. Prøv igjen, eller kontakt UKM Norge Support.');
		}
		while ($r = Query::fetch($res)) {
			$persons[] = new Person(intval($r['p_id']));
		}
		$videresendte_innslag = array();
		
		foreach ($persons as $person) {
			$p_innslag = $person->innslag();
			$har_innslag = false;
			foreach($p_innslag as $pinn) {
				# Hvis innslaget er videresendt til festivalen:
				if (in_array($pinn, $m_innslag_id)) {
					$innslaget = new Innslag($pinn);
					$innslaget->loadGEO();
					$videresendte_innslag[] = $innslaget;
					$har_innslag = true;
				}
			}
			if ($har_innslag) {
				$view_data['personer'][] = $person;
			}
		}

		if (empty($videresendte_innslag)) {
			return $this->render('UKMDeltaBundle:Sjekk:notvideresendt.html.twig', $view_data);
		}
		$view_data['innslag'] = $videresendte_innslag;
		return $this->render('UKMDeltaBundle:Sjekk:sjekk.html.twig', $view_data);
	}

	public function createSjekkAction () {
		// Hent mobilnummer fra current bruker
		$user = $this->get('ukm_user')->getCurrentUser();
		$NUMBER = $user->getPhone();

		# HVIS NUMMER ALLEREDE FINNES I DATABASEN
		$qry = new Query("SELECT * FROM `ukm_sjekk` WHERE `phone` = '#mobile'", array('mobile' => $NUMBER));
		$res = $qry->getArray();
		if ($res) {
			#$url = 'https://delta.ukm.no/sjekk/'.$NUMBER.'/'.$res['hash'];
			$hash = $res['hash'];
		}
		else {
			# Generer hash
			$data = $NUMBER + time();
			$hash = hash("sha256", $data);
			$hash = substr($hash, 32, 8);
			## Lagre mobilnummer og hash i databasen
			$qry = new Insert("ukm_sjekk");
			$qry->add('phone', $NUMBER);
			$qry->add('hash', $hash);
			$res = $qry->run();

			if($res != 1) {
				error_log('UKMSJEKK: Klarte ikke å lagre i databasen fra Delta. Nr: '.$NUMBER);
				die();
			}
			#$url = 'https://delta.ukm.no/sjekk/'.$NUMBER.'/'.$hash;
		}
			
		$view_data['mobile'] = $NUMBER;
		$view_data['hash'] = $hash;
		# Redirect
		return $this->redirectToRoute('ukm_sjekk_homepage', $view_data);
	}
}
