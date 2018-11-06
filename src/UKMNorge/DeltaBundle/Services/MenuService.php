<?php
namespace UKMNorge\DeltaBundle\Services;

use UKMNorge\DesignBundle\Utils\Sitemap\Section;
use stdClass;

class MenuService {

		private $menu = null;

		public function __construct($container, $sitemap) {
			$this->container = $container;
			$this->sitemap = $sitemap;
		}

		public function get() {
			if( null == $this->menu ) {
				$this->_load();
			}
		
			$section = new Section( 'extra', $this->menu );

			$this->sitemap->addSection( $section );
			return $section;
		}

		private function _load() {
			$userManager = $this->container->get('ukm_user');
			$user = $userManager->getCurrentUser();
			$router = $this->container->get('router');
			$menu = [
				'url' => '//delta.ukm.no',
				'title' => 'Din side',
				'pages' => []
			];

			if( is_object( $user ) && $user !== null ) {
				$menu['pages'][] = [
					'id' => 'dinside',
					'url' => $router->generate('ukm_delta_ukmid_homepage'),
					'title' => 'Din side'
				];

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
					'id' => 'loggut',
					'url' => $router->generate('fos_user_security_logout'),
					'title' => 'Logg ut'
				];
				
				$menu['pages'][] = [
					'id' => 'kontakt',
					'url' => $router->generate('ukm_delta_ukmid_support'),
					'title' => 'Support',
				];

				if( empty( $user->getFacebookId() ) ) {
					$menu['pages'][] = [
						'id' => 'facebook',
						'url' => $router->generate('ukm_fb_connect'),
						'title' => 'Koble til facebook'
					];
				}
			}
			
			$this->menu = $menu;
		}
}