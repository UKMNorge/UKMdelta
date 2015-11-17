<?php
require_once('UKM/sql.class.php');
require_once('UKM/person.class.php');
require_once('UKM/inc/ukmlog.inc.php');
require_once('UKM/monstring.class.php');

function create_innslag($bt_id, $season, $pl_id, $kommune, $contact=false){
	$tittellos = in_array($bt_id, array(4,5,8,9,10));
	
#	if($tittellos && !$contact)
		
	

	$band = new SQLins('smartukm_band');
	$band->add('b_season', $season);
	$band->add('b_status', 8);
	$band->add('b_name', 'Nytt innslag');
	$band->add('b_kommune', $kommune);
	$band->add('b_year', date('Y'));
	$band->add('b_subscr_time', time());
	$band->add('bt_id', $bt_id);
	
	if(is_object($contact))
		$band->add('b_contact', $contact->g('p_id'));

#	echo $band->debug();
	$bandres = $band->run();
	$b_id = $band->insid();
	
	$tech = new SQLins('smartukm_technical');
	$tech->add('b_id', $b_id);
	$tech->add('pl_id', $pl_id);
	$techres = $tech->run();
	
	$rel = new SQLins('smartukm_rel_pl_b');
	$rel->add('pl_id', $pl_id);
	$rel->add('b_id', $b_id);
	$rel->add('season', $season);
	$relres = $rel->run();
	
	return $b_id;
}

function getBandtypeID($type) {
	switch($type) {
		case 'musikk':
		case 'dans':
		case 'teater':
		case 'litteratur':
		case 'scene': 			$bt_id = 1; break;
		case 'film':
		case 'video': 			$bt_id = 2; break;
		case 'utstilling': 		$bt_id = 3; break;
		case 'konferansier': 	$bt_id = 4; break;
		case 'nettredaksjon': 	$bt_id = 5; break;
		case 'matkultur':		$bt_id = 6; break;
		case 'arrangor': 		$bt_id = 8; break;
		case 'sceneteknikk': 	$bt_id = 9; break;
		case 'annet': 			$bt_id = 1; break;
		default:				$bt_id = false;
	}

	return $bt_id;
}

function getBandTypeFromID($id) {
	switch($id) {
		case 1: 	$type = 'scene';				break;
		case 2: 	$type = 'video';				break;
		case 3: 	$type = 'utstilling';			break;
		case 4: 	$type = 'konferansier';			break;
		case 5: 	$type = 'nettredaksjon';		break;
		case 6: 	$type = 'matkultur';			break;
		case 8: 	$type = 'arrangor';				break;
		case 9: 	$type = 'sceneteknikk';			break;
		default: 	$type = 'annet';				break;
	}

	return $type;
}

class innslag {
	## Attributtkontainer
	var $info = array();
	var $personer_loaded = false;
	var $items_loaded = false;
	var $personer = array();
	var $items = array();
	var $warnings = array();

	
	public function update($field, $post_key=false, $force = false) {
		if(!$post_key)
			$post_key = $field;
		if(!$force && $_POST[$post_key] == $_POST['log_current_value_'.$post_key])
			return true;
			
		// Tekniske krav skal i en annen tabell enn resten
		if (in_array($field, array('td_demand', 'td_konferansier'))) {
			$qry = new SQLins('smartukm_technical', array('b_id'=>$this->info['b_id']));
			if (!$force)
				UKMlog('smartukm_technical',$field,$post_key,$this->info['b_id']);
		}
		// Alt annet
		else {
			$qry = new SQLins('smartukm_band', array('b_id'=>$this->info['b_id']));
			if (!$force)
				UKMlog('smartukm_band',$field,$post_key,$this->info['b_id']);
		}
			
		$qry->add($field, $_POST[$post_key]);
		$this->info[$field] = $_POST[$post_key];
		return $qry->run();
	}
	
	public function clear($field, $post_key=false) {
		if(!$post_key)
			$post_key = $field;
					
		$this->update($field, '', true);
	}
	
	public function addPerson($p_id) {
		$qry = new SQLins('smartukm_rel_b_p');
		$qry->add('b_id', $this->info['b_id']);
		$qry->add('p_id', $p_id);
		$qry->add('season', $this->g('b_season'));
		$qry->add('b_p_year', $this->g('b_season'));
		$res = $qry->run();
		
#		UKM_loader('private');
#		if(UKM_private()){
		if($this->info['b_contact'] == 0) {
			$_POST['autofix_b_contact'] = $p_id;
			$_POST['log_current_value_autofix_b_contact'] = 0;
			$this->update('b_contact', 'autofix_b_contact');	
		}
#		}
	}
	
	public function removePerson($p_id) {
		$qry = new SQLdel('smartukm_rel_b_p', 
							array('b_id'=>$this->info['b_id'], 
								  'p_id'=>$p_id, 
								  'season'=>$this->g('b_season'), 
								  'b_p_year'=>$this->g('b_season')));
		return $qry->run();
	}
	
	public function delete() {		
		$qry = new SQLins('smartukm_band', array('b_id'=>$this->g('b_id')));
		$qry->add('b_status', 99);
		$res = $qry->run();
		#echo $qry->debug();
		
		$_POST['b_status'] = 99;
		UKMlog('smartukm_band','b_status','b_status',$this->g('b_id'));
		
		$deleteFromStat = new SQLdel('ukm_statistics', 
									array('season' => $this->g('b_season'),
										  'b_id' => $this->g('b_id')
										  )
									);
		$deleteFromStat->run();

		return ($res===1);
	}
	
	public function lagre() {
		$qry = new SQLins("smartukm_band", array('b_id' => $this->info['b_id'] ) );
		$count = 0;

		$td_qry = new SQLins("smartukm_technical", array('b_id' => $this->info['b_id']));
		$td_count = 0;
	
		if( is_array( $this->lagre ) ) {
			foreach( $this->lagre as $key => $value ) {
				if(strpos($key, 'td_') === 0) {
					$td_qry->add($key, $value);
					$td_count++;
				}
				else {
					$qry->add( $key, $value );	
					$count++;
				}
			}
		}
		if ($td_count>0) {
			$td_qry->run();
		}

		if ($count > 0) {
			$qry->run();
		}

		$this->lagre = array();
	}

	## Henter et innslags innebygde attributter fra b_id
	public function innslag($b_id, $onlyifsubscribed=true) {
		$qry = "SELECT `smartukm_band`.*, 
					   `smartukm_band_type`.`bt_name`, 
					   `smartukm_band_type`.`bt_form`,
					   `td`.`td_demand`,
					   `td`.`td_konferansier`
				FROM `smartukm_band`
				LEFT JOIN `smartukm_band_type` ON (`smartukm_band_type`.`bt_id` = `smartukm_band`.`bt_id`)
				LEFT JOIN `smartukm_technical` AS `td` ON (`td`.`b_id` = `smartukm_band`.`b_id`)
				WHERE `smartukm_band`.`b_id` = '".$b_id."' "
			 .  ($onlyifsubscribed ? "AND `smartukm_band`.`b_status` = 8" : '')
				;
		$sql = new SQL($qry);
		$res = $sql->run('array');
		#echo $sql->debug();
#$res = $wpdb->get_row($qry,'ARRAY_A');
		
		$this->info = $res;
		$this->b_id = $this->id = $this->info['b_id'];
		
		$this->_loadKategoriogsjanger();
		
		## Korrigerer innslagsnavnet hvis det skulle være noe galt
		$this->correctName();
		$this->__charset();
		
		$this->_time_status_8();
	}

	## Gi ny verdi (value) til attributten (key)
	## OBS: Lagrer ikke!
	public function set($key, $value){
		$this->info[$key] = $value;
		$this->lagre[$key] = $value;
	}
	
	## Returnerer verdien til attributten (key)
	public function g($key) {	return $this->get($key);	}
	public function get($key) {
		if(is_array($this->info[$key]))
			return $this->info[$key];
			
		return utf8_encode($this->info[$key]);	
	}
		
	## Returnerer hele objektet for var-dump
	public function info(){
		return $this->info;
	}
	
	## Henter bilde

	public function loadBTIMG() {
		switch($this->info['bt_id']) {
			case 1: 	$img = 'scene';				break;
			case 2: 	$img = 'video';				break;
			case 3: 	$img = 'utstilling';		break;
			case 4: 	$img = 'konferansier';		break;
			case 5: 	$img = 'nettredaksjon';		break;
			case 6: 	$img = 'matkultur';			break;
			case 8: 	$img = 'arrangor';			break;
			case 9: 	$img = 'sceneteknikk';		break;
			default: 	$img = 'annet';				break;
		}
		$this->info['btimgName'] = $img;
		$this->info['btimg'] = '<img src="http://ico.ukm.no/subscription/'.$img.'.png" style="border:none;" height="60" /><br />';
		$this->info['btimg_url'] = 'http://ico.ukm.no/subscription/'.$img.'.png';
	}
	
	public function loadGEO()
	{
		$qry = new SQL("SELECT `smartukm_kommune`.`name` AS `kommune`, 
					   `smartukm_kommune`.`id` AS `kommuneID`,
					   `smartukm_fylke`.`name` AS `fylke`, 
					   `smartukm_fylke`.`id` AS `fylkeID`
						FROM `smartukm_kommune`
						JOIN `smartukm_fylke` ON (`smartukm_fylke`.`id` = `smartukm_kommune`.`idfylke`)
						WHERE `smartukm_kommune`.`id` = '#id'",
						array('id'=>$this->info['b_kommune']));
		$res = $qry->run('array');
		$this->info['kommune_utf8'] = utf8_encode($res['kommune']);
		$this->info['kommune'] = ($res['kommune']);
		$this->info['kommuneID'] = $res['kommuneID'];
		$this->info['fylke_utf8'] = utf8_encode($res['fylke']);
		$this->info['fylke'] = ($res['fylke']);
		$this->info['fylkeID'] = $res['fylkeID'];
				 
	}
	
	private function _loadKategoriogsjanger() {
		$this->info['kategori'] = ($this->info['bt_id']==1 
									? ($this->info['b_kategori'] == 'scene' 
										? 'Musikk' 
										: ucfirst(utf8_decode($this->info['b_kategori']))
									  )
									: $this->info['bt_name']
								  );
		
		$sjanger  = !empty($this->info['b_sjanger']) ? $this->info['b_sjanger'] : '';
		$kategori = !empty($this->info['b_kategori']) ? ucfirst($this->info['b_kategori']) : '';
			
		if($kategori == $sjanger) $sjanger = '';
		if(!empty($sjanger) && !empty($kategori)) {
			$katOgSjan = $kategori . ' - ' . $sjanger;
		} elseif(!empty($sjanger)) {
			$katOgSjan = $sjanger;
		} elseif(!empty($kategori)) {
			$katOgSjan = $kategori;
		} else {
			$katOgSjan = '';	
		}
		if($this->tittellos()){
			$this->info['kategori_og_sjanger'] = $this->info['bt_name'];
			$this->info['b_kategori'] = $this->info['bt_name'];
		}
		else
			$this->info['kategori_og_sjanger'] = ($katOgSjan);
	}	
	
	private function __charset() {
		//$this->info['bt_name'] = utf8_encode($this->info['bt_name']);

		//$this->info['b_name'] = mb_detect_encoding($this->info['b_name'], "UTF-8") == "UTF-8" 
		//					 ? utf8_encode($this->info['b_name'])
		//					 : ($this->info['b_name']);
	}
	
	private function correctName() {
		if(empty($this->info['b_name']))
			$this->info['b_name'] = 'Innslag uten navn';
	}
	
	private function _time_status_8() {
		if(array_key_exists('bt_id', $this->info) || !in_array($this->info['bt_id'], array(1,2,3,6,7))) {
			$this->info['time_status_8'] = $this->info['b_subscr_time'];
			return;
		}

		$qry = new SQL("SELECT `log_time` FROM `ukmno_smartukm_log`
						WHERE `log_b_id` = '#bid'
						AND `log_code` = '22'
						ORDER BY `log_id` DESC",
						array('bid'=>$this->info['b_id']));
		$this->info['time_status_8'] = $qry->run('field','log_time');

		if(empty($this->info['time_status_8']))
			$this->info['time_status_8'] = $this->info['b_subscr_time'];
	}
	
	public function tittellos(){
		//var_dump($this->info);
		if (array_key_exists('bt_id', $this->info)) {
			return !in_array($this->info['bt_id'], array(1,2,3,6,7));
		}
		else {
			return 1;
		}
	}
	
	####################################################################################
	## FUNKSJONER RELATERT TIL PERSONER I INNSLAGET
	####################################################################################
	## Returnerer en liste over innslags-ID'er. Hvis listen ikke er laget, last den inn
	public function kontaktperson() {
		if(!isset($this->kontaktperson))
			$this->kontaktperson = new person($this->g('b_contact'));
		return $this->kontaktperson;
	}
	
	public function setKontaktperson($p_id) {
		$qry = new SQLins('smartukm_band', array('b_id'=>$this->info['b_id']));
		$qry->add('b_contact', $p_id);
		$qry->run();
	}
	
	public function personer() {
		if(!$this->personer_loaded)
			$this->load_personer();
			
		return $this->personer;	
	}
	
	public function personObjekter() {
		if(!$this->personer_loaded)
			$this->load_personer();
		
		$persons = array();
		
		foreach( $this->personer as $person ) {
			$persons[] = new person($person['p_id'], $this->g('b_id'));
		}
		
		return $persons;
	}
	
	public function ikke_videresendte_personObjekter() {
		$this->ikke_videresendte_personer();
		$persons = array();
		foreach( $this->ikke_videresendte_personer as $person ) {
			$persons[] = new person($person['p_id'], $this->g('b_id'));
		}
		
		return $persons;
	}
	
	public function ikke_videresendte_personer() {
		if(!isset($this->ikke_videresendte_personer))
			$this->_load_ikke_videresendte_personer();
		return $this->ikke_videresendte_personer;
	}

	public function num_personer() {
		return sizeof($this->personer());
	}
	
	public function videresendte($pl_til) {
		$this->visKunVideresendte = $pl_til;
	}
	
	private function _load_ikke_videresendte_personer() {
		$this->ikke_videresendte_personer = array();
		$qry = $this->_load_personer_qry("LEFT JOIN `smartukm_fylkestep_p` AS `fs` ON (`fs`.`p_id` = `smartukm_participant`.`p_id`) "," AND `fs`.`b_id` IS NULL");
		$qry = new SQL($qry);
		$res = $qry->run();
		if($res&&mysql_num_rows($res)>0)
			while($set = mysql_fetch_assoc($res))
				$this->ikke_videresendte_personer[] = array('p_id'=>$set['p_id'], 
															'p_firstname'=>utf8_encode($set['p_firstname']), 
															'p_lastname'=>utf8_encode($set['p_lastname']), 
															'instrument'=>utf8_encode($set['instrument']), 
															'p_phone'=>$set['p_phone']);

	}
	
	private function _load_personer_qry($extraJoin='', $extraWhere='') {
		return "SELECT `smartukm_participant`.`p_id`, `p_firstname`, `p_lastname`, `instrument`, `p_phone` FROM `smartukm_participant`
				JOIN `smartukm_rel_b_p` ON (`smartukm_rel_b_p`.`p_id` = `smartukm_participant`.`p_id`)"
				.$extraJoin."
				WHERE `smartukm_rel_b_p`.`b_id` = ".$this->info['b_id']
				.$extraWhere."
				GROUP BY `smartukm_participant`.`p_id`
				ORDER BY `smartukm_participant`.`p_firstname`, `smartukm_participant`.`p_lastname` ASC";
	}
	
	public function load_personer() {
		if(isset($this->visKunVideresendte) 
		&& is_numeric($this->visKunVideresendte) 
		&& $this->info['bt_form'] != 'smartukm_titles_scene') {
			$extraJoin = 'JOIN `smartukm_fylkestep_p` AS `fs` 
							ON (`fs`.`p_id` = `smartukm_participant`.`p_id`) ';
			$extraWhere  = ' AND `fs`.`pl_id` = '.$this->visKunVideresendte.'';
		} else
			$extraJoin = $extraWhere = '';
		
		$qry = $this->_load_personer_qry($extraJoin, $extraWhere);
		
		$qry = new SQL($qry);
		$res = $qry->run();
		#$res = $wpdb->get_results($qry,'ARRAY_A');
		if($res&&mysql_num_rows($res)>0)
			while($set = mysql_fetch_assoc($res))
				$this->personer[] = array('p_id'=>$set['p_id'], 'p_firstname'=>utf8_encode($set['p_firstname']), 'p_lastname'=>utf8_encode($set['p_lastname']), 'instrument'=>utf8_encode($set['instrument']), 'p_phone'=>$set['p_phone']);
		
		$this->personer_loaded = true;
	}

	####################################################################################
	## FUNKSJONER RELATERT TIL RELATERTE ELEMENTER I INNSLAGET
	####################################################################################
	## Returnerer en liste over bilder, nyheter og videoer. Hvis listen ikke er laget, last den inn
	public function related_items() {
		if(!$this->items_loaded)
			$this->_load_related_items();
			
		return $this->items;	
	}

	private function _load_related_items() {
		require_once('UKM/related.class.php');
		$rel = new related($this->info['b_id']);
		$rel = $rel->get();
		if(is_array($rel))
			foreach($rel as $id => $info) {
				if($id == 0)
					$id = $info['rel_id'];
				$this->items[$info['post_type']][$id] = $info;
			}
		
		$this->_load_related_tv();
		$this->items_loaded = true;
	}
	
	private function _load_related_tv() {
		require_once('UKM/tv.class.php');
		require_once('UKM/tv_files.class.php');
		
		$tv_files = new tv_files('band', $this->id);
		while($tv = $tv_files->fetch()) {
			$this->items['tv'][$tv->id] = $tv;
		}
	}
	####################################################################################
	## FUNKSJONER FOR PLAY-/SINGBACKFILER (IKKE OFFENTLIG TILGJENGELIGE FILER)
	####################################################################################
	public function playbackToString() {
		return $this->har_playback() ? 'HAR PLAYBACK' : 'nei';
	}

	public function playback() {
		if( !isset( $this->playback ) )
			$this->_load_playback();
		return $this->playback;
	}
	
	public function har_playback() {
		if( !isset( $this->playback ) )
			$this->_load_playback();
		
		return sizeof( $this->playback ) > 0;
	}
	
	private function _load_playback() {
		require_once('UKM/playback.class.php');
		$this->playback = array();

		$sql = new SQL("SELECT `pb_id` 
						FROM `ukm_playback`
						WHERE `b_id` = '#bid'",
					   array('bid' => $this->g('b_id'))
					  );
		$res = $sql->run();

		if( $res ) {
			while( $r = mysql_fetch_assoc( $res ) ) {
				$this->playback[] = new playback( $r['pb_id'] );
			}
		}
	}

	####################################################################################
	## 
	####################################################################################

	
	public function videresend($videresendFra, $videresendTil, $tittel = 0) {
		if ($videresendFra == 0 || $videresendTil == 0)
			return false;
			
		if (!is_numeric($tittel))
			$tittel = 0;			
		
		$season = new monstring($videresendFra);
		$season = $season->g('season');
			
		$test_relasjon = new SQL("SELECT * FROM `smartukm_rel_pl_b`
								  WHERE `pl_id` = '#plid'
								  AND `b_id` = '#bid'
								  AND `season` = '#season'",
								  array('plid'=>$videresendTil, 'bid'=>$this->g('b_id'), 'season'=>$season));
		$test_relasjon = $test_relasjon->run();	
		
		if(mysql_num_rows($test_relasjon)==0) {		
			$videresend_innslag_relasjon = new SQLins('smartukm_rel_pl_b');
			$videresend_innslag_relasjon->add('pl_id', $videresendTil);
			$videresend_innslag_relasjon->add('b_id', $this->g('b_id'));
			$videresend_innslag_relasjon->add('season', $season);
			$videresend_innslag_relasjon->run();
		}
		
		$test_fylkestep = new SQL("SELECT * FROM `smartukm_fylkestep`
									  WHERE `pl_id` = '#plid'
									  AND `pl_from` = '#pl_from'
									  AND `b_id` = '#bid'
									  AND `t_id` = '#t_id'",
									  array('plid'=>$videresendTil, 
									  		'bid'=>$this->g('b_id'),
											'pl_from'=>$videresendFra,
											't_id'=>$tittel));
		$test_fylkestep = $test_fylkestep->run();

		if (mysql_num_rows($test_fylkestep)==0) {
			$videresend_innslag = new SQLins('smartukm_fylkestep');
			$videresend_innslag->add('pl_id', $videresendTil);
			$videresend_innslag->add('pl_from', $videresendFra);
			$videresend_innslag->add('b_id', $this->g('b_id'));
			$videresend_innslag->add('t_id', $tittel);
			$videresend_innslag->run();
		}
		return true;
	}
	
	public function avmeld($videresendFra, $videresendTil, $tittel = 0, $slettRelasjoner = true) {
		if ($videresendFra == 0 || $videresendTil == 0)
			return false;
			
		if (!is_numeric($tittel))
			$tittel = 0;
		
		$monstring = new monstring($videresendFra);
		// DENNE BØR OPPDATERES, SLIK AT DEN ENDRER B_STATUS OG LOGGER VED AVMELDING P&aring; KOMMUNENIV&aring;!
		if ($monstring->g('pl_type') == 'kommune')
			return false;
		
		$season = $monstring->g('season');
			
		#if(!$slettedeRelasjoner || (is_array($slettedeRelasjoner) && !in_array($this->g('b_id'),$slettedeRelasjoner))) {
		if($slettRelasjoner) {
			$slett_relasjon = new SQLdel('smartukm_rel_pl_b',
								array('pl_id'=>$videresendTil,
									  'b_id'=>$this->g('b_id'),
									  'season'=>$season));
			$slett_relasjon->run();
			#$slettedeRelasjoner[] = $this->g('b_id');
		}
		
		if( $tittel == 0 ) {
			$slett_relasjon = new SQLdel('smartukm_fylkestep',
											array('pl_id'=>$videresendTil,
												  'pl_from'=>$videresendFra,
												  'b_id'=>$this->g('b_id')
												 )
										);
		} else {
			$slett_relasjon = new SQLdel('smartukm_fylkestep',
											array('pl_id'=>$videresendTil,
												  'pl_from'=>$videresendFra,
												  'b_id'=>$this->g('b_id'),
												  't_id'=>$tittel
												 )
										);
		}
		$slett_relasjon->run();
#		return $slettedeRelasjoner;

		$this->statistikk_oppdater();
		return true;
	}

	
	public function bilde($width=120,$size='thumbnail',$wrap=true) {
		require_once('UKM/related.class.php');

		$rel = new related($this->info['b_id']);
		$img = $rel->getLastImage($size);
		if(!$img)		
			return $this->info['btimg'.($wrap?'':'_url')];
		if(!$wrap)
			return $img;
		return '<img src="'.$img.'" width="'.$width.'" />';
	}
	
	public function fjernfraforestilling($c_id) {
		if(!is_numeric($c_id)||!is_numeric($this->g('b_id'))||$c_id==0)
			return false;
		$qry = new SQLdel('smartukm_rel_b_c',
					array('b_id'=>$this->g('b_id'),
						  'c_id'=>$c_id));
		return $qry->run() == 1;
	}
	
	public function forestillinger($pl_id) {
		if(!isset($this->forestillinger))
			$this->_load_forestillinger($pl_id);
		return $this->forestillinger;
	}
	
	public function antall_hendelser($pl_id, $unntatt=array()){ return $this->antall_forestillinger($pl_id, $unntatt);}
	public function antall_forestillinger($pl_id, $unntatt=array()) {
		if(!isset($this->forestillinger))
			$this->_load_forestillinger($pl_id);
			
		if(!is_array($unntatt))
			$unntatt = array($unntatt);
		
		if(sizeof($unntatt)>0) {
			$antall_foretillinger = 0;
			foreach($this->forestillinger as $c_id => $rekkefolge) {
				if(in_array($c_id, $unntatt))
					continue;
				$antall_forestillinger++;
			}
			return $antall_forestillinger;
		}
		return sizeof($this->forestillinger);
	}
	
	private function _load_forestillinger($pl_id) {
		$this->forestillinger = array();
		$sql = new SQL( 'SELECT `c`.`c_id`, `b`.`order` FROM `smartukm_concert` as `c`, `smartukm_rel_b_c` as `b`
		                 WHERE `c`.`pl_id` = '.$pl_id.'
		                 AND `b`.`b_id` = '. $this->info['b_id'] .'
		                 AND `c`.`c_id` = `b`.`c_id`
		                 ORDER BY `c_start` ASC');
		$res = $sql->run();
		if(!$res)
			return;
		while($r = mysql_fetch_assoc($res))
			$this->forestillinger[$r['c_id']] = $r['order']+1;
	}
	
	public function ikke_videresendte_titler() {
		if(!isset($this->ikke_videresendte_titler))
			$this->_load_ikke_videresendte_titler();
		
		return $this->ikke_videresendte_titler;
		
	}
	private function _load_ikke_videresendte_titler() {
		$this->ikke_videresendte_titler = array();
		$sql = new SQL("SELECT `t_id` FROM `#form` WHERE `b_id` = '#bid'",
						array('form'=>$this->g('bt_form'), 'bid'=>$this->g('b_id')));

		$res = $sql->run();
		if($res&&mysql_num_rows($res)>0) {
			while($r = mysql_fetch_assoc($res)){
				$videresendt = new SQL("SELECT * FROM `smartukm_fylkestep`
										WHERE `b_id` = '#bid'
										AND `t_id` = '#tid'",
										array('bid'=>$this->g('b_id'), 'tid'=>$r['t_id']));
				$videresendt = $videresendt->run();
				if(mysql_num_rows($videresendt)!=0)
					continue;
				
				$this->ikke_videresendte_titler[] = new tittel($r['t_id'],$this->g('bt_form'));
			}
		}
	}

	////
	private function _load_titler($pl_id,$forwardToPLID=false,$uavhengig_av_monstring=false) {
		require_once('UKM/tittel.class.php');
			
		$this->titler = array();
		$place = new monstring($pl_id);
		$sql = new SQL("SELECT `t_id` FROM `#form` WHERE `b_id` = '#bid'",
						array('form'=>$this->g('bt_form'), 'bid'=>$this->g('b_id')));

		$res = $sql->run();
		if($res&&mysql_num_rows($res)>0) {
			while($r = mysql_fetch_assoc($res)){
				/// LUK UT TITLER HVIS FYLKESMØNSTRING
				if($place->g('type')=='fylke' && !$uavhengig_av_monstring) {
					if( $forwardToPLID ) {
						$videresendt = new SQL("SELECT * FROM `smartukm_fylkestep`
												WHERE `b_id` = '#bid'
												AND `t_id` = '#tid'
												AND `pl_id` = '#plto'
												AND `pl_from` = '#plfrom'",
												array(	'bid'=>$this->g('b_id'),
														'tid'=>$r['t_id'],
														'plto' => $forwardToPLID,
														'plfrom' => $pl_id
													)
											);
					} else {
						$videresendt = new SQL("SELECT * FROM `smartukm_fylkestep`
												WHERE `b_id` = '#bid'
												AND `t_id` = '#tid'",
												array('bid'=>$this->g('b_id'), 'tid'=>$r['t_id']));
					}
					$videresendt = $videresendt->run();
					if(mysql_num_rows($videresendt)==0)
						continue;
				}
				/// LUK UT TITLER HVIS LANDSMØNSTRING
				elseif($place->g('type')=='land' && !$uavhengig_av_monstring) {
					// !! !! !! OBS !! !! !! //
					// Er det her korrekt &aring; bruke forwardToPLID ?
					// Burde det ikke være $this->g('pl_id')?
					// 08.09.2012
					// 26.09.2012 Endret, tror logikken stemmer
					$videresendt = new SQL("SELECT * FROM `smartukm_fylkestep`
											WHERE `b_id` = '#bid'
											AND `t_id` = '#tid'
											AND `pl_id` = '#plid'",
											array('bid'=>$this->g('b_id'),
												  'tid'=>$r['t_id'],
												  /*'plid'=>$forwardToPLID));*/
												  'plid'=>$pl_id));
					$videresendt = $videresendt->run();
					if(mysql_num_rows($videresendt)==0) {
					// 20.01.2013 Lagt til sjekk nr 2 for at APIet skal håndtere gamle videresendinger
					$landstep = new SQL("SELECT * FROM `smartukm_landstep`
											WHERE `b_id` = '#bid'
											AND `t_id` = '#tid'",
											array('bid'=>$this->g('b_id'),
												  'tid'=>$r['t_id']));
	#				if($_SERVER['REMOTE_ADDR'] == '188.113.121.10')
	#					echo $landstep->debug();
					$landstep = $landstep->run();

						if(mysql_num_rows($landstep)==0) {
							continue;
						}
					}
						
				}

				$this->titler[] = new tittel($r['t_id'],$this->g('bt_form'));
			}
		}
	}
	
	public function titler($pl_id, $forwardToPLID=false, $uavhengig_av_monstring=false) {
		if(!isset($this->titler))
			$this->_load_titler($pl_id, $forwardToPLID, $uavhengig_av_monstring);
		return $this->titler;
	}
	
	public function varighet($pl_id, $forwardToPLID=false) {
		if(!isset($this->info['varighet']))
			$this->kalkuler_titler($pl_id, $forwardToPLID);
		return $this->g('varighet');
	}
	
	public function tid($pl_id, $forwardToPLID=false) {
		if(!isset($this->info['tid']))
			$this->kalkuler_titler($pl_id, $forwardToPLID);
		return $this->g('tid');
	}
	
	public function kalkuler_titler($pl_id, $forwardToPLID=false) {
		$titler = $this->titler($pl_id);
		$varighet = 0;
		foreach($titler as $tittel) {
			$varighet += (int) $tittel->g('varighet');
		}
		$this->info['varighet'] = $varighet;
		$this->info['tid'] = $this->_secondtominutes($varighet);
		$this->info['antall_titler'] = sizeof($titler);
		$this->info['antall_titler_lesbart'] = sizeof($titler)==1?'1 tittel':sizeof($titler).' titler';
	}
	
	private function _secondtominutes($sec) {
		$q = floor($sec / 60);
		$r = $sec % 60;
		
		if ($q == 0)
			return $r.' sek';
			
		if ($r == 0)
			return $q.' min';
		
		return $q.'m '.$r.'s';
	}
	
	public function editable() {
		if($this->g('b_status')==8)
			return true;
		return time() > (7*24*3600 + $this->g('b_subscr_time'));
	}
	
	public function warning_array($pl_id) {
		$this->_load_warnings($pl_id);
		return $this->warnings;
	}
	public function warnings($pl_id) {
		if(!isset($this->info['warnings']))
			$this->_load_warnings($pl_id);
		return $this->g('warnings');
	}
	
	private function _load_warnings($pl_id){
		$warning = array();
		###
		$this->personer();
		if(sizeof($this->personer)==0)
			$warning[] = 'innslaget har ingen deltakere';
		###
		if(!in_array($this->g('bt_id'),array(4,5,8,9))){
			$this->kalkuler_titler($pl_id);
			if($this->g('antall_titler')==0)
				$warning[] = 'innslaget har ingen titler (og vil derfor ikke kunne settes opp i et program)';
			if($this->g('antall_titler')>3)
				$warning[] = 'innslaget har tenkt &aring; delta '.$this->g('antall_titler').' titler';

			if($this->g('bt_id')==1) {
				if($this->g('varighet')<30)
					$warning[] = 'innslaget har en total varighet p&aring; '.$this->g('tid').' (mindre enn 10 sekunder)';
				if($this->g('varighet')>600)
					$warning[] = 'innslaget har en total varighet p&aring; '.$this->g('tid').' (mindre enn 10 sekunder)';
			}
		}
		$k = $this->g('b_kategori');
		$t = $this->g('td_demand');
		if(empty($t) && (in_array($k, array('musikk','dans','teater')) || substr($k,0,5)=='annet'))
			$warning[] = 'innslaget har ingen tekniske behov';
		
		$place = new monstring($pl_id);
		$forestillinger = $place->forestillinger();
		// Legg til arrayet med varsler i klassearrayet.
		$this->warnings = $warning;
		$this->info['warnings'] = ucfirst(implode(', ', $warning));
	}
	////
	
	
	
	public function statistikk_oppdater() {
	
		$sqldel = new SQLdel('ukm_statistics',
							 array('season' => $this->get('b_season'),
							 	   'b_id' => $this->get('b_id')));
		$sqldel->run();
		
		$this->loadGEO();
		if($this->get('b_status')==8) {
			foreach ($this->personer() as $p) { // behandle hver person
				$person = new person($p["p_id"]);
				
				$time = $this->get('time_status_8');
				
				if (strlen($time) <= 1) {
					$time = $this->get('b_season')."-01-01T00:00:01Z";
				} else {
					$time = date("Y-m-d\TH:i:s\Z" , $this->get('time_status_8'));
				}
				
				$kommuneID = $this->get("kommuneID");
				$fylkeID = $this->get("fylkeID");
				
				$gjestekommune = str_replace((string) $fylkeID, '', (string) $kommuneID);
				if( $gjestekommune == '90' ) {
    				continue;
				}
				
				// PRE 2011 does not contain kommune in database.
				// Fake by selecting first kommune of mønstring
				if(empty($kommuneID)) {
/*
					$kommuneID = $monstring->info['kommuner'][0]['id'];
					$fylkeID = $monstring->get('fylke_id');
*/
				}
				
				$stats_info = array(
					"b_id" => $this->get("b_id"), // innslag-id
					"p_id" => $person->get("p_id"), // person-id
					"k_id" => $kommuneID, // kommune-id
					"f_id" => $fylkeID, // fylke-id
					"bt_id" => $this->get("bt_id"), // innslagstype-id
					"subcat" => $this->get("b_kategori"), // underkategori
					"age" => $person->getAge() == '25+' ? 0 : $person->getAge(), // alder
					"sex" => $person->kjonn(), // kjonn
					"time" =>  $time, // tid ved registrering
					"fylke" => false, // dratt pa fylkesmonstring?
					"land" => false, // dratt pa festivalen?
					"season" => $this->get('b_season') // sesong
				);
				
				// faktisk lagre det 
				$qry = "SELECT * FROM `ukm_statistics`" .
						" WHERE `b_id` = '" . $stats_info["b_id"] . "'" .
						" AND `p_id` = '" . $stats_info["p_id"] . "'" .
						" AND `k_id` = '" . $stats_info["k_id"] . "'"  .
						" AND `season` = '" . $stats_info["season"] . "'";
				$sql = new SQL($qry);
				
				// Sjekke om ting skal settes inn eller oppdateres
				if (mysql_num_rows($sql->run()) > 0)
					$sql_ins = new SQLins('ukm_statistics', array(
						"b_id" => $stats_info["b_id"], // innslag-id
						"p_id" => $stats_info["p_id"], // person-id
						"k_id" => $stats_info["k_id"], // kommune-id
						"season" => $stats_info["season"], // kommune-id
					) );
				else 
					$sql_ins = new SQLins("ukm_statistics");
				
				// Legge til info i insert-sporringen
				foreach ($stats_info as $key => $value) {
					$sql_ins->add($key, $value);
				}
				$sql_ins->run();
			}
		}
	}

	##################
	#	VALIDATION   #
	##################
	function validateBand2($bid) {
		require_once('UKM/sql.class.php');
		global $SEASON;
		$feedback = array();

	    $band = new SQL("SELECT *, `smartukm_band`.`b_id` AS `the_real_b_id` FROM `smartukm_band` 
	                         JOIN `smartukm_participant` ON (`smartukm_participant`.`p_id` = `smartukm_band`.`b_contact`)
	                         LEFT JOIN `smartukm_band_type` ON (`smartukm_band_type`.`bt_id` = `smartukm_band`.`bt_id`)
	                         LEFT JOIN `smartukm_technical` ON (`smartukm_technical`.`b_id` = `smartukm_band`.`b_id`)
	                         WHERE `smartukm_band`.`b_id` = '#b_id'",
	                                array('b_id'=>$bid));

	    $band = $band->run('array');
	    
	    switch($band['bt_id']) {
	        ## SCENE
	        case 1:
	            ## CHECK NAME AND SJANGER
	            $test_2 = $this->nameAndSjanger($band);       
	            ## CHECK DESCRIPTION
	            $test_3 = $this->description($band);
	            ## CHECK CONTACT PERSON
	            $test_4 = $this->contact_person($band);
	            ## CHECK PARTICIPANTS
	            $test_5 = $this->participants($band); // Returns an array now
	            ## CHECK TITLES
				if($band['b_kategori'] == "Dans"||$band['b_kategori'] == 'dans'||$band['b_kategori']=='dance')
		            $test_6 = $this->titles($band, array('t_name','t_coreography','t_time'), 'danser');			
				elseif($band['b_kategori'] == "litteratur"||$band['b_kategori'] == 'litterature')
		            $test_6 = $this->titles($band, array('t_name','t_time'), 'titler');
				elseif($band['b_kategori'] == "teater"||$band['b_kategori'] == 'theatre')
		            $test_6 = $this->titles($band, array('t_name','t_titleby','t_time'), 'stykker');
				elseif(strpos($band['b_kategori'],'annet') !== false)
					$test_6 = $this->titles($band, array('t_name', 't_time'), 'titler');
				else 
		            $test_6 = $this->titles($band, array('t_name','t_musicby','t_time'));
				## CHECK TECHNICAL DEMANDS
				$test_1 = $this->technical($band);
	            break;
			## VIDEO
	    	case 2: 
	            ## CHECK NAME AND SJANGER
	            $test_2 = $this->nameAndSjanger($band);       
	            ## CHECK DESCRIPTION
	            $test_3 = $this->description($band);
	            ## CHECK CONTACT PERSON
	            $test_4 = $this->contact_person($band);
	            ## CHECK PARTICIPANTS
	            $test_5 = $this->participants($band);
	            ## CHECK TITLES
				$test_6 = $this->titles($band, array('t_v_title','t_v_format','t_v_time'));
				## CHECK TECNICAL
				$test_1 = true;
				break;
			## EXHIBITION
	    	case 3: 
	            ## CHECK NAME AND SJANGER
	            $test_2 = $this->name($band);       
	            ## CHECK DESCRIPTION
	            $test_3 = $this->description($band);
	            ## CHECK CONTACT PERSON
	            $test_4 = $this->contact_person($band);
	            ## CHECK PARTICIPANTS
	            $test_5 = $this->participants($band);
	            ## CHECK TITLES
	            $test_6 = $this->titles($band, array('t_e_title','t_e_type'));
				## CHECK TECHNICAL DEMANDS
				$test_1 = true;
				break;
			## MATKULTUR
	    	case 6: 
	            ## CHECK NAME
	            $test_2 = $this->name($band);       
	            ## CHECK DESCRIPTION
	            $test_3 = $this->description($band);
	            ## CHECK CONTACT PERSON
	            $test_4 = $this->contact_person($band);
	            ## CHECK PARTICIPANTS
	            $test_5 = $this->participants($band);
	            ## CHECK TITLES
	            $test_6 = $this->titles($band, array('t_o_function','t_o_comments'));
				## CHECK TECHNICAL DEMANDS
				$test_1 = true;
				break;
			## OTHER ON SCENE
			case 7:
			    ## CHECK NAME AND SJANGER
	            $test_2 = $this->name($band);       
	            ## CHECK DESCRIPTION
	            $test_3 = $this->description($band);
	            ## CHECK CONTACT PERSON
	            $test_4 = $this->contact_person($band);
	            ## CHECK PARTICIPANTS
	            $test_5 = $this->participants($band);
	            ## CHECK TITLES
	            $test_6 = $this->titles($band, array('t_o_function','t_o_experience'));
				## CHECK TECHNICAL DEMANDS
				$test_1 = $this->technical($band);
				break;
	    }
	    
		if($band['bt_id'] == (1 || 2 || 3 || 6 || 7)) {
			$textFeedback = '';
			$status = 1;
			for($i=1; $i<8; $i++) {
				$check = 'test_'.$i;
				if(!isset($$check)) {
					$status++;
					continue;
				}
				if($$check !== true) {
					//$textFeedback .= str_replace('<br />', "\r\n", $$check) . "\r\n";
					if (is_array($$check)) {
						$feedback = array_merge_recursive($feedback, $$check);
					}
					else {
						$feedback[] = $$check;
					}
				}
				else $status++;
			}
		} else {
			$status = 8;
		}
		## CHECK THE VALIDATION OF THE BAND
		$updated = new SQL("UPDATE `smartukm_band` 
						   SET `b_status` = '#status',
						   `b_status_text` = '#text'
						   WHERE `b_id` = '#b_id'",
						   array('status'=>($status>$band['b_status']?$status:$band['b_status']),
								 'text'=>'Deprecated in UKMdelta',
								 'b_id'=>$bid
								 )
						   );
		// echo $updated->debug() . '<br>';
		$updated = $updated->run();
		// var_dump($updated);
		// die();
		if($status == 8 && (int)$band['b_status'] < 8) {
			if(function_exists('logIt')) { 
				logIt($bid, 22, (int)$band['b_status']);
			}
		}
		else {
			if(function_exists('logIt')) { 
				logIt($bid, 21, (int)$band['b_status'] .' => '.(int)$status);
			}
		}

		return array($status, $feedback);
		//return array($status, (empty($textFeedback) ? 'Alt er OK!' : '<h2>F&oslash;lgende obligatoriske felt er ikke utfylt:</h2>'.$textFeedback), $feedback); 
	}


	###########################################################
	########     TITLES							 ##############
	###########################################################
	function titles($b, $fields, $tittelnavn=false) {
		
		# FETCH ALL FIELDS
		$qry = new SQL("SELECT * FROM `#table` WHERE `b_id` = '#b_id'", 
						   array('table'=>$b['bt_form'], 'b_id'=>$b['the_real_b_id']));
		$res = $qry->run();

		# FIND TITLE KEY
		switch($b['bt_id']) {
			case 1:
				$titleKey = 't_name';
				if(!$tittelnavn)
				$tittelnavn = 'l&aring;ter';
				break;
			case 2:
				$titleKey = 't_v_title';
				if(!$tittelnavn)
				$tittelnavn = 'filmer';
				break;
			case 3:
				$titleKey = 't_e_title';
				if(!$tittelnavn)
				$tittelnavn = 'kunstverk';
				break;
			default:
				if(!$tittelnavn)
				$titleKey = 't_o_function';
				break;
		}

		if(!$tittelnavn)
			$tittelnavn = 'titler';
		
		$header = '<strong>'.ucfirst($tittelnavn).':</strong><br />';

		## IF NO TITLES, RETURN
		if(mysql_num_rows($res)==0)
			return array('titler' => array('tittel.mangler'));

		$missing = array();
		
		## LOOP ALL TITLES
		while($title = mysql_fetch_assoc($res)) {
			for($i=0; $i<sizeof($fields); $i++) {
				if(empty($title[$fields[$i]])) {
					## IF DANCE AND NOT MANDATORY FIELD
					if($b['b_kategori']=='dans' && in_array($fields[$i],array('t_musicby','t_titleby')))
						continue;
					## IF THEATRE AND NOT MANDATORY FIELD
					elseif($b['b_kategori']=='teater' && in_array($fields[$i],array('t_musicby','t_titleby','t_coreography')))
						continue;
					## IF THEATRE AND NOT MANDATORY FIELD
					elseif($b['b_kategori']=='annet' && in_array($fields[$i],array('t_musicby','t_titleby','t_coreography')))
						continue;
		
					$missing[] = $title[$titleKey].$fields[$i];
					break;
				}
			}
		}
		## IF NOTHING WRONG, RETURN TRUE
		if(empty($missing)) return true;
		
		return array('titler' => $missing);
	}	

	###########################################################
	########     LOOP AND CHECK PARTICIPANTS	 ##############
	###########################################################
	function participants($band) {
		$header = '<strong>Deltakere:</strong><br />';
		global $SEASON;
		$whatwrong = array();
		$participants = new SQL("SELECT * FROM `smartukm_participant`
	    							JOIN `smartukm_rel_b_p` ON (`smartukm_rel_b_p`.`p_id` = `smartukm_participant`.`p_id`)
	                                WHERE `smartukm_rel_b_p`.`b_id` = '#bid'
									GROUP BY `smartukm_participant`.`p_id`", 
	                                array('bid'=>$band['the_real_b_id'], 'season'=>$SEASON));
	    $participants = $participants->run();
		## IF NO PARTICIPANTS
		if(mysql_num_rows($participants)==0)
			return array('innslag' => array('innslag.ingendeltakere'));
			//return $header. ' Det er ingen deltakere i innslaget';

		## LOOP FOR PARTICIPANTS
		while($p = mysql_fetch_assoc($participants)) {
	    	$test = $this->participant($p);
	        if($test !== true) {
	        	$whatwrong[] = array($p['p_firstname'].' '.$p['p_lastname'], $test);
	        }
		}
	    
	    if(empty($whatwrong)) return true;
		
	    return array('personer' => $whatwrong);
	}

	###########################################################
	########     CHECK ONE PARTICIPANT			 ##############
	###########################################################
	function participant($p) {
		$whatmissing = array();
		if(empty($p['p_firstname']) && strlen($p['p_firstname']) < 3)
	    							 							$whatmissing[] = 'person.fornavn';
		if(empty($p['p_lastname']) && strlen($p['p_lastname']) < 3)
	    							 							$whatmissing[] = 'person.etternavn';
	#	if(empty($p['p_email']))	
	 #   														$whatmissing .= ' &nbsp;- E-postadresse mangler<br />';
	    if(empty($p['p_phone']) || strlen($p['p_phone'])!==8)
	    														$whatmissing[] = 'person.phone';
	    if(empty($p['instrument']))
	    														$whatmissing[] = 'person.rolle';
	  	
	    if(empty($whatmissing)) return true;
	    
	    return $whatmissing; // Denne returneres kun til participants, så trenger ikke å wrappes i et array!
	}

	###########################################################
	########     CHECK NAME OF BAND				 ##############
	###########################################################
	function name($band) {
		$whatmissing = array();
	  	if(empty($band['b_name'])) 								$whatmissing[] = 'innslag.navn';
		
	    if(empty($whatmissing)){
		    return true;	
		}
	    return array('innslag' => $whatmissing);	}

	###########################################################
	########     CHECK NAME AND SJANGER FOR BAND ##############
	###########################################################
	function nameAndSjanger($band) {
		$wrong = array();
	    if(empty($band['b_name'])) 							$wrong[] = 'innslag.navn';
	    if(empty($band['b_sjanger']))						$wrong[] = 'innslag.sjanger';				
		
		if(empty($wrong)){
		    return true;	
		}
		return array('innslag' => $wrong);
	}

	###########################################################
	########     CHECK DESCRIPTION				 ##############
	###########################################################
	function description($band) {
		$whatmissing = array();
		if(empty($band['td_konferansier']))						$whatmissing[] = 'innslag.beskrivelse';
	    elseif(strlen($band['td_konferansier']) < 20)			$whatmissing[] = 'innslag.beskrivelseLengde';
	   	elseif($band['td_konferansier'] == 'innslag.beskrivelseLengde') $whatmissing[] = 'innslag.beskrivelse';
	    
	    if(empty($whatmissing)){
		    return true;	
		}
	    return array('innslag' => $whatmissing);
	}

	###########################################################
	########     CHECK TECHNICAL DEMANDS		 ##############
	###########################################################
	function technical($band) {
		$whatmissing = array();
		global $lang;
		if(empty($band['td_demand']))							$whatmissing[] = 'td.mangler';
	    elseif(strlen($band['td_demand']) < 5)					$whatmissing[] = 'td.lengde';
		
		if(empty($whatmissing)){
		    return true;	
		}
	    return array('teknisk' => $whatmissing);
	}

	###########################################################
	########    CHECK CONTACT PERSON			 ##############
	###########################################################
	function contact_person($b) {
		$whatmissing = array();
		if(empty($b['p_firstname']) && strlen($b['p_firstname']) < 3)
	    	$whatmissing[] = 'kontakt.fornavn';

		if(empty($b['p_lastname']) && strlen($b['p_lastname']) < 3)
	    	$whatmissing[] = 'kontakt.etternavn';

		if(empty($b['p_email']) || !validEmail($b['p_email']))	
	    	$whatmissing[] = 'kontakt.epost';

	    if(empty($b['p_phone']) || strlen($b['p_phone'])!==8)
	    	$whatmissing[] = 'kontakt.telefon';

		if($b['p_phone'] == '12345678' 
		|| $b['p_phone'] == '00000000' 
		|| $b['p_phone'] == '11111111' 
		|| $b['p_phone'] == '22222222' 
		|| $b['p_phone'] == '33333333' 
		|| $b['p_phone'] == '44444444' 
		|| $b['p_phone'] == '55555555' 
		|| $b['p_phone'] == '66666666' 
		|| $b['p_phone'] == '77777777' 
		|| $b['p_phone'] == '88888888' 
		|| $b['p_phone'] == '99999999' 
		|| $b['p_phone'] == '12341234' 
		|| $b['p_phone'] == '87654321' 
		|| $b['p_phone'] == '23456789' 
		|| $b['p_phone'] == '98765432')
	    	$whatmissing[] = 'kontakt.telefonugyldig';

	    if(empty($b['p_adress']) || strlen($b['p_adress']) < 3)
	    	$whatmissing[] = 'kontakt.adresse';
	    if( empty($b['p_postnumber']) || (strlen($b['p_postnumber']) !==4 || strlen($b['p_postnumber']) === 3 && $b['p_postnumber'] < 200))
	    	$whatmissing[] = 'kontakt.postnummer';
	  	
	    if(empty($whatmissing)) return true;
	    return array('kontakt' => $whatmissing);
	}

	###########################################################
	########     EMAIL-VALIDATIONS				 ##############
	###########################################################
	function validEmail($email) {
	   $isValid = true;
	   $atIndex = strrpos($email, "@");
	   if (is_bool($atIndex) && !$atIndex)
	   {
	      $isValid = false;
	   }
	   else
	   {
	      $domain = substr($email, $atIndex+1);
	      $local = substr($email, 0, $atIndex);
	      $localLen = strlen($local);
	      $domainLen = strlen($domain);
	      if ($localLen < 1 || $localLen > 64)
	      {
	         // local part length exceeded
	         $isValid = false;
	      }
	      else if ($domainLen < 1 || $domainLen > 255)
	      {
	         // domain part length exceeded
	         $isValid = false;
	      }
	      else if ($local[0] == '.' || $local[$localLen-1] == '.')
	      {
	         // local part starts or ends with '.'
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $local))
	      {
	         // local part has two consecutive dots
	         $isValid = false;
	      }
	      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
	      {
	         // character not valid in domain part
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $domain))
	      {
	         // domain part has two consecutive dots
	         $isValid = false;
	      }
	      else if
	(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
	                 str_replace("\\\\","",$local)))
	      {
	         // character not valid in local part unless 
	         // local part is quoted
	         if (!preg_match('/^"(\\\\"|[^"])+"$/',
	             str_replace("\\\\","",$local)))
	         {
	            $isValid = false;
	         }
	      }
	      if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
	      {
	         // domain not found in DNS
	         $isValid = false;
	      }
	   }
	   return $isValid;
	}

}


