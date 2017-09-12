<?php

namespace UKMNorge\DesignBundle\Utils\Sitemap;

use UKMNorge\DesignBundle\Utils\Sitemap\Pages;
use UKMNorge\DesignBundle\Utils\Sitemap\Page;

class Section {
	var $id = null;
	var $url = null;
	var $title = null;
	var $pages = null;
	var $color = 'b89dcc';
	
	public function __construct( $id, $data ) {
		$this->id = $id;
		$this->url = $data['url'];
		$this->title = $data['title'];
		$this->color = (isset( $data['color'] ) && !empty( $data['color'] )) ? $data['color'] : false;
		
		$this->pages = new Pages();
		$this->_loadPages( $data );
	}
	
	public function getId() {
		return $this->id;
	}
	public function getUrl() {
		return $this->url;
	}
	public function getTitle() {
		return $this->title;
	}
	public function Sitemap() {
		return $this->title;
	}
	public function getPages() {
		return $this->pages;
	}
	public function getColor() {
		return $this->color;
	}
	
	private function _loadPages( $data ) {
		if( isset( $data['pages'] ) && is_array( $data['pages'] ) ) {
			foreach( $data['pages'] as $id => $page ) {
				$page_object = new Page( $page );
				$this->getPages()->add( $page_object );
			}
		}
	}
}
