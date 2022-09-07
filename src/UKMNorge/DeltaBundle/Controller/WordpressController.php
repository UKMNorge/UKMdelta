<?php

namespace UKMNorge\DeltaBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use UKMNorge\DeltaBundle\Entity\HideCampaign;
use UKMNorge\APIBundle\Services\PersonService;
use UKMNorge\Wordpress\LoginToken;
use DateTime;
use Symfony\Component\HttpFoundation\RedirectResponse;
use UKMmail;
use Exception;

class WordpressController extends Controller {
    /**
     * Lager token i ukm_delta_wp_login_token, og sender brukeren til Wordpress-Autologin.
     * PersonService håndterer oppdatering av link-tabeller om nødvendig.
     * 
     * Nås kun av brukere som er logget inn i Delta.
     * 
     */
    public function connectAction() {
        $log = $this->container->get('logger');
        $log->info("WordpressController: Connect-action startet.");
        $personService = new PersonService($this->container);
        $user = $personService->hentCurrentUser();
    
        # Lag token:
        try {
            $log->info("WordpressController: Oppretter token for bruker.");
            $loginToken = LoginToken::create( $user->getId(), $personService->hentWordpressUserId($user) );
        } catch (Exception $e) {
            $log->error("WordpressController:ConnectAction - klarte ikke å opprette LoginToken. Feilkode: ".$e->getCode().", melding: ".$e->getMessage());
            $this->addFlash('danger', "Klarte ikke å opprette innlogging hos Wordpress. ".$e->getMessage());
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
        # Finn URL, send brukeren til Wordpress eller tilbake til hjem-siden med feilmeldinga ved problem.
        try {
            $log->info("WordpressController:ConnectAction: Henter wordpress-login-URL.");
            $url = $personService->hentWordpressLoginURL($loginToken);    
            return new RedirectResponse($url);
        } catch (Exception $e) {
            $log->error("WordpressController:ConnectAction - klarte ikke å hente WordpressLoginURL og redirecte. Feilkode: ".$e->getCode().", melding: ".$e->getMessage());
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }
    }
}