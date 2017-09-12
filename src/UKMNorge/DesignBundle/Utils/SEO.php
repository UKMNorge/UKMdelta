<?php
namespace UKMNorge\DesignBundle\Utils;
	
class SEO {
	public $canonical;
	public $description;
	public $author;
	public $site_name;
	public $type;
	public $title;
	public $section;
	public $image;
	public $published;
	
	public $fb_admins;
	public $fb_app_id;
	
	public $google_site_verification;
	
	public function __construct() {
		
	}
	
	public function getCanonical(){
		return $this->canonical;
	}
	public function setCanonical( $canonical ){
		$this->canonical = $canonical;
		return $this;
	}
	
	public function getDescription(){
		return $this->description;
	}
	public function setDescription( $description ){
		$this->description = $description;
		return $this;
	}
	
	public function getAuthor(){
		return $this->author;
	}
	public function setAuthor( $author ){
		$this->author = $author;
		return $this;
	}
	
	public function getSiteName(){
		return $this->site_name;
	}
	public function setSiteName( $site_name ){
		$this->site_name = $site_name;
		return $this;
	}
	
	public function getType(){
		return $this->type;
	}
	public function setType( $type ){
		$this->type = $type;
		return $this;
	}
	
	public function getTitle(){
		return $this->title;
	}
	public function setTitle( $title ){
		$this->title = $title;
		return $this;
	}
	
	public function getSection(){
		return $this->section;
	}
	public function setSection( $section ){
		$this->section = $section;
		return $this;
	}
	
	public function getImage(){
		return $this->image;
	}
	public function setImage( $image ){
		$this->image = $image;
		return $this;
	}
	
	public function getPublished(){
		return $this->published;
	}
	public function setPublished( $published ){
		$this->published = $published;
		return $this;
	}


	public function getFBAdmins() {
		return $this->$fb_admins;
	}
	public function setFBAdmins( $fb_admin_ids ) {
		$this->fb_admins = $fb_admin_ids;
		return $this;
	}
	public function getFBAppId() {
		return $this->fb_app_id;
	}
	public function setFBAppId( $fb_app_id ) {
		$this->fb_app_id = $fb_app_id;
		return $this;
	}
	
	
	
	public function getGoogleSiteVerification() {
		return $this->google_site_verification;
	}
	public function setGoogleSiteVerification( $site_verification_id ) {
		$this->google_site_verification = $site_verification_id;
		return $this;
	}

}