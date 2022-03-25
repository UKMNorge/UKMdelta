<?php
namespace UKMNorge\DeltaBundle\Services;

use UKMNorge\DesignBundle\UKMDesign;
use UKMNorge\DesignBundle\UKMDesign\Sitemap;
use UKMNorge\Design\Sitemap\Section;
use stdClass;

class MenuService {

        private $section = null;
        private $container;

		public function __construct($container) {
            $this->container = $container;
        }
        
        public function getPages() {
            if( is_null($this->section)) {
                $this->_loadSection();
            }
            return $this->section->getPages();
        }

        private function _loadSection() {
            $this->section = new Section(
                'site_extras',
                'https://delta.ukm.no/ukmid/',
                'Din side',
                $this->_loadPages()
            );
        }

		private function _loadPages() {
			$userManager = $this->container->get('ukm_user');
			$user = $userManager->getCurrentUser();
			$router = $this->container->get('router');
            
            $menu = [];
			// Kun vis disse knappene dersom brukeren er logget inn.
			if( is_object( $user ) && $user !== null ) {
				if( date('m') < 8 && date('m') > 3 ) {
                    $menu['pages'][] = [
						'id' => 'sjekk',
						'url' => $router->generate('ukm_sjekk_create'),
						'title' => 'Sjekk info'
					];
				}
					$menu['pages'][] = [
					'id' => 'profil',
					'url' => $router->generate('ukm_delta_ukmid_contact'),
					'title' => 'Rediger profil'
				];

				$menu['pages'][] = [
					'id' => 'passord',
					'url' => $router->generate('fos_user_change_password'),
					'title' => 'Bytt passord'
				];
				
				
				$menu['pages'][] = [
					'id' => 'endrefotoreservasjon',
					'url' => $router->generate('ukm_delta_endre_fotoreservasjon'),
					'title' => 'Endre fotoreservasjon',
				];

				if( empty( $user->getFacebookId() ) ) {
					$menu['pages'][] = [
						'id' => 'facebook',
						'url' => $router->generate('ukm_fb_connect'),
						'title' => 'Koble til facebook'
					];
				}

				$menu['pages'][] = [
					'id' => 'kontakt',
					'url' => $router->generate('ukm_delta_ukmid_support'),
					'title' => 'Support',
				];


				$menu['pages'][] = [
					'id' => 'loggut',
					'url' => $router->generate('fos_user_security_logout'),
					'title' => 'Logg ut'
				];
			}
			
			return $menu;
		}
}