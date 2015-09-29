<?php
namespace MariusMandal\FokusBundle\Services;
use Symfony\Component\DependencyInjection\ContainerInterface;
use MariusMandal\FokusBundle\Entity\System;
use Exception;
use stdClass;

class SenderService {

	public function __construct( $price, $sender ) {
		$this->price = $price;
		$this->sender = $sender;
	}

}