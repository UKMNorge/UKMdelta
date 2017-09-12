<?php
	
namespace UKMNorge\DesignBundle\Utils;

use Symfony\Component\Yaml\Yaml;
use UKMNorge\DesignBundle\Utils\Sitemap\Section;

class Sitemap {
	static $config = [];
	static $sections = [];
	
	
	public static function loadFromYamlfile( $filepath ) {
		self::$config = Yaml::parse( file_get_contents( $filepath ) );
		
		foreach( self::$config as $key => $val ) {
			self::addSection( new Section( $key, $val ) );
		}
	}
	
	public static function getSection( $id ) {
		foreach( self::$config as $order => $section ) {
			if( $id == $section->getId() ) {
				return $section;
			}
		}
		return false;
	}
	
	public static function addSection( $section ) {
		if( !isset( self::$config[ $key ] ) ) {
			self::$sections[] = $section;
		}
		return sizeof( self::$sections );
	}
	
	public static function getSections() {
		return self::$sections;
	}
}