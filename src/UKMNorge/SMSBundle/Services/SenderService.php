<?php
namespace UKMNorge\SMSBundle\Services;
use Symfony\Component\DependencyInjection\ContainerInterface;
use MariusMandal\FokusBundle\Entity\System;
use Exception;
use stdClass;

class SenderService {

	public function __construct( $price, $sender ) {
		$this->price = $price;
		$this->sender = $sender;
	}

	public function sendSMS( $to, $message ) {
		throw new Exception('Attempted to send SMS (damn!)');
	}
}