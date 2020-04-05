<?php

namespace UKMNorge\SMSBundle\Entity;

/**
 * SMS
 *
 *
 * $SMS = new SMS($system_id, $user_id);
 * $SMS->text($message)->to($recipients_csv_or_single)->from($sender)->ok();
 *
 */
class SMS {
	var $price = 0.40;
	var $bogus = array(44444444, 99999999);
	
	var $error = false;
	var $error_messages = array();

	var $transaction_id;
	var $sender_id = false;
	
	var $message = '';
	var $recipients = array();
	var $from = '';
	var $from_dirty = '';

	public function __construct($system_id, $user_id, $pl_id=0) {
		$this->id_system= $system_id;
		$this->id_user	= $user_id;		
		$this->id_place = $pl_id;
	}
	
	public function text($message) {	
		$this->message = $message;
		$this->_length();
		return $this;
	}
	
	public function to($recipients) {
		if(!empty($recipients)) {
			$this->recipients = array_merge( $this->recipients, explode(',', str_replace(array(' ',' ',' '),'',strip_tags($recipients))) );
		}
		
		return $this;
	}
	
	public function from($sender) {
		$this->from_dirty = $sender;
		$this->from = $this->_clean($sender);
		return $this;
	}
	
	public function report() {
		$this->_validate();
		if($this->error) {
			return implode(',', $this->error_messages);
		}
		
		$this->_credits();
		
		return $this->credits;
	}
	
	public function ok() {
		$this->report();
		$this->_create_transaction();		
		$this->_add_recipients();
		$this->sendSMS();
	}
	
	public function sendSMS() {	
		// !! !! !!
		// SHOULD BULK-SEND 10 AT A TIME
		// !! !! !!
		foreach($this->recipients as $recipient) {
			$this->_send($recipient);	
		}
	}
	
	private function _send($recipient) {
		$sms_raw_result = $this->_sveve($recipient);
		$sms_result = $this->_sveve_parse($sms_raw_result);
		
		if($sms_result)
			$this->_sent($recipient);
		else
			$this->_not_sent($recipient);

		return $sms_result;
	}
	
	private function _sent($recipient) {
		$this->_send_status($recipient, 'sent');
	}
	
	private function _not_sent($recipient) {
		$this->_send_status($recipient, 'error');
/*
		if($_SERVER['REMOTE_ADDR'] == '81.0.146.162')
			var_dump( $this->sveve_parsed_response);
*/
			
		if( isset($this->sveve_parsed_response->errors->fatal) ) {
			$this->_error('SVEVE ERROR: '. $this->sveve_parsed_response->errors->fatal );
		} elseif( isset($this->sveve_parsed_response->errors->error) ) {
			$this->_error('SVEVE ERROR: '. $this->sveve_parsed_response->errors->error->message );
		} else {
			$this->_error('SVEVE ERROR: Ukjent feil oppsto');
		}		
	}
	
	private function _send_status($recipient, $status) {
		$transaction_recipient_update = new SQLins('log_sms_transaction_recipients',
													array('t_id' => $this->transaction_id,
														  'tr_recipient' => $recipient));
		$transaction_recipient_update->add('tr_status', $status);
		$transaction_recipient_update->run();
	}
	
	private function _sveve($recipient) {
		$url = 'http://www.sveve.no/SMS/SendSMS'
			.  '?user='.UKM_SVEVE_ACCOUNT
			.  '&to='.(int)$recipient
			.  '&from='.$this->from
			.  '&msg='.urlencode($this->message);
/*
		if($_SERVER['REMOTE_ADDR']=='81.0.146.162')
			var_dump($url);
*/
		$curl = new UKMCURL();
		$curl->request($url);
		return $curl->result;
	}
	
	private function _sveve_parse($response) {
		$response = simplexml_load_string($response);
		$response = $response->response;
		
		$this->sveve_parsed_response = $response;

		if(isset($response->errors))
			return false;
		
		return true;
	}

	
	private function _add_recipients() {
		// ADD RECIPIENTS
		foreach($this->recipients as $recipient) {
			$recipient_add = new SQLins('log_sms_transaction_recipients');
			$recipient_add->add('t_id', 		$this->transaction_id);
			$recipient_add->add('tr_recipient', $recipient);
			$recipient_add->add('tr_status', 	'queued');
			$recipient_add->run();
		}
	}
	
	private function _create_transaction() {
		// CREATE TRANSACTION
		$transaction = new SQLins('log_sms_transactions');
		$transaction->add('pl_id', 		$this->id_place);
		$transaction->add('t_system', 	$this->id_system);
		$transaction->add('wp_username',$this->id_user);
		$transaction->add('t_credits',  $this->credits);
		$transaction->add('t_comment',	$this->message);
		$transaction->add('t_action',	'sendte_sms_for');
		
		$transaction_res = $transaction->run();
		$this->transaction_id = $transaction->insid();
		
		return $this->transaction_id;
	}
	
	private function _credits() {
		$this->credits = $this->num_textmessages * sizeof($this->recipients);
	}
	
	private function _length() {
		if(strlen($this->message) <= 160)
			$this->num_textmessages = -1;
		else
			$this->num_textmessages = -1 * round(strlen($this->message) / 154);
	}
	
	private function _clean($string, $allowed='A-Za-z0-9-') {
		return preg_replace('/[^'.$allowed.'.]/', '', $string);
	}
	
	// VALIDATE SENDER
	// 

	// VALIDATION	
	private function _validate() {
		$this->_validate_sender();
		$this->_validate_message();
		$this->_validate_recipients();
		$this->_validate_from();
		
	}
	
	private function _validate_from() {
		if($this->from != $this->from_dirty)
			$this->_error('Ugyldige tegn i avsender-navn. (Skrevet inn: "'.$this->from_dirty.'" skulle vært "'.$this->from.'")');
	}
		
	private function _validate_recipients() {
		if(sizeof($this->recipients)==0) {
			$this->_error('Ingen mottakere lagt til');
		}
		array_unique($this->recipients);

		foreach($this->recipients as $key => $recipient) {
			// REMOVE FACEBOOK 3 SPECIAL CHARS
			if( strlen( $recipient )== 11 && (int)$recipient == 0 )
				$recipient = substr($recipient, 3);

			// Phone is always int
			$recipient = (int) $recipient;
		
			// Remove empty or not norwegian phone
			if($recipient == 0 || strlen($recipient) != 8) {
//				echo "REMOVED: $recipient: not 8 long<br />";
				unset($this->recipients[$key]);
				continue;
			}
			
			// Remove not mobile
			if(!$this->_is_mobile($recipient)) {
//				echo "REMOVED: $recipient: not mobile<br />";
				unset($this->recipients[$key]);
				continue;
			}
		}
	}

	private function _is_mobile($int) {
		//if( !4-serien && !9-serien) {
		if ( !(90000000 < $int && $int < 99999999) && !(40000000 < $int && $int < 50000000) )		
			return false;

		if( in_array($int, $this->bogus) )
			return false;

		return true;
	}
	
	private function _validate_message() {
		if(empty($this->message))
			$this->_error('Meldingen er tom');
	}
	
	private function _validate_sender() {
		if(empty($this->id_system) || !$this->id_system) {
			$this->_error('Ukjent system-ID');
			return false;
		}
		
		if($this->id_system == 'wordpress' && empty($this->id_user)) {
			$this->_error('Ukjent bruker-ID');
			return false;
		}
		
		if($this->id_system == 'wordpress' && (int)$this->id_place == 0) {
			$this->_error('Mangler mønstrings-ID (pl_id)');
			return false;
		}
	}
	
	// ERRORS
	private function _error($message) {
		$this->error = true;
		$this->error_messages[] = $message;
	}
}
?>
