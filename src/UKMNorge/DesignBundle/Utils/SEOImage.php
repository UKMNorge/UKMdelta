<?php
namespace UKMNorge\DesignBundle\Utils;

class SEOImage {
	public $url;
	public $width;
	public $height;
	public $type;
	
	public function __construct( $url, $width=null, $height=null, $type=null ) {
		$this->url = $url;
		if( $width !== null && $height !== null ) {
			$this->width = $width;
			$this->height = $height;
		}
		if( $type !== null ) {
			$this->type = $type;
		}
	}
	
	public function getType() {
		return $this->type;
	}
	public function setType( $type ) {
		$this->type = $type;
		return $this;
	}
	
	public function getUrl(){
		return $this->url;
	}
	public function setUrl( $url ){
		$this->url = $url;
		return $this;
	}
	
	public function getWidth(){
		return $this->width;
	}
	public function setWidth( $width ){
		$this->width = $width;
		return $this;
	}
	
	public function getHeight(){
		return $this->height;
	}
	public function setHeight( $height ){
		$this->height = $height;
		return $this;
	}
}