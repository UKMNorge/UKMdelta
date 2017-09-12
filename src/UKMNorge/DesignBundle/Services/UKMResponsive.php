<?php
	
namespace UKMNorge\DesignBundle\Services;

class UKMResponsiveService {
	static $nav = [];
	
	public static function setNav( $nav ) {
		self::$nav = $nav;
		return self;
	}
	public static function getNav() {
		return self::$nav;
	}
}