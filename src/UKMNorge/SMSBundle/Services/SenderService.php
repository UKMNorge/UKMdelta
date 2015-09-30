<?php
namespace UKMNorge\SMSBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use stdClass;

require_once('UKM/sms.class.php');

class SenderService {

	public function __construct($container, $system_id, $price, $sender ) {
		$this->system_id = $system_id;
		$this->price = $price;
		$this->sender = $sender;
		$this->container = $container;
		
		$this->SMSapi = new \SMS( $this->system_id, '0');
	}

	public function sendSMS( $recipient, $message ) {
		#$this->SMSapi->text($message)->to($recipient)->from($this->sender)->ok();
		throw new Exception( 'Utviklingsmodus, sms ikke sendt: ('. $recipient.': '. $message .')' );
		$result = $this->report();
		
		if( !is_integer( $result ) ) {
			throw new Exception($result);
		}

		return true;
	}
	
	public function report() {
		return $this->SMSapi->report();
	}
}