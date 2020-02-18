<?php
namespace UKMNorge\DeltaBundle\Services;

use UKMNorge\DesignBundle\UKMDesign;
use UKMNorge\DesignBundle\UKMDesign\Sitemap;
use UKMNorge\Design\Sitemap\Section;
use stdClass;

class MenuService {

		private $menu = null;

		public function __construct($container, $ukmdesign) {
			$this->container = $container;
			$this->ukmdesign = $ukmdesign;
			$this->log = $container->get('logger');
			$this->log->info("Initialized Delta MenuService");
		}

		public function get() {
			$this->log->info("Getting Delta Menu from MenuService");

			if( null == $this->menu ) {
				$this->_load();
			}
			
			$section = new Section('extra', $this->menu['url'], $this->menu['title'], $this->menu);
			$this->log->info("Adding Delta Menu to sitemap sections");
			$this->ukmdesign->getSitemap()->addSection( $section );

			return $section;
		}

		private function _load() {
			$this->log->info("Loading Delta Menu");
			$userManager = $this->container->get('ukm_user');
			$user = $userManager->getCurrentUser();
			$router = $this->container->get('router');
			$menu = [
				'url' => '//delta.ukm.no',
				'title' => 'Din side',
				'pages' => []
			];

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