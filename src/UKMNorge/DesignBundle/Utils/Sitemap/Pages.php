<?php

namespace UKMNorge\DesignBundle\Utils\Sitemap;

use UKMNorge\DesignBundle\Utils\Section\Page;

class Pages {
	var $pages = [];
	
	public function __construct() {
	}
	
	public function add( $page ) {
		$this->pages[] = $page;
	}
	public function getAll() {
		return $this->pages;
	}
	public function get( $id ) {
		foreach( $this->pages as $page ) {
			if( $id == $page->getId() ) {
				return $page;
			}
		}
		return false;
	}
}
