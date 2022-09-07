<?php

namespace UKMNorge\UserBundle\Controller;


/* FROM PARENT */
	use Symfony\Bundle\FrameworkBundle\Controller\Controller;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\Security\Core\Security;
	use Symfony\Component\Security\Core\SecurityContextInterface;
	use Symfony\Component\Security\Core\Exception\AuthenticationException;
/* E.O FROM PARENT */

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use UKMNorge\APIBundle\Services\SessionService;
use Symfony\Component\HttpFoundation\Session\Session;


use FOS\UserBundle\Controller\SecurityController as BaseController;
use UKMNorge\UserBundle\UKMUserEvents;
use UKMNorge\UserBundle\Entity\User;
use UKMCurl;

class UKMSecurityController extends BaseController {
    
	public function loginAction(Request $request, $renderWithoutLayout=false)
    {	

        $this->app_id = $this->getParameter('facebook_client_id');
        $session = $request->getSession();

        if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->deltaFBLoginURL = 'https://delta.ukm.dev/app_dev.php/fblogin';
        } 
        else {
            $this->deltaFBLoginURL = 'https://delta.ukm.no/fblogin';
        }

        // Er dette en redirect-forespørsel? I så fall, lagre i session.
        $rdirurl = '';
        $rdirtoken = '';
        if ($request->query->get('rdirurl')) {
            $session->set('rdirurl', $request->query->get('rdirurl'));
            $session->set('rdirtoken', $request->query->get('token'));
        }

        // Lagre redirect-url som Facebook skal sende oss til ved retur
        $redirectURL = $this->deltaFBLoginURL.$rdirtoken;

        // Ber en ekstern tjeneste om å få mer informasjon tilbake?
        if( $request->query->get('scope') ) {
            $session->set('scope', $request->query->get('scope'));
        } 
        else {
            // Vask session for scope dersom det ikke er satt i request. For å unngå feil ved re-innlogging++
            $session->remove('scope');
            $session->remove('infoQueue');
        }

        // Hvis brukeren er innlogget, redirect de til rett sted.
        $securityContext = $this->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED') || $securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
            
            // Get the LoginSuccessHandler, which will redirect as proper
            $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
            $request = Request::createFromGlobals();
            $usertoken = $this->get('security.token_storage')->getToken();

            $response = $handler->onAuthenticationSuccess($request, $usertoken);
            return $response;
        }

        // Fyll inn data på innloggings-siden + CSRF-sjekk
        if (class_exists('\Symfony\Component\Security\Core\Security')) {
            $authErrorKey = Security::AUTHENTICATION_ERROR;
            $lastUsernameKey = Security::LAST_USERNAME;
        } else {
            // BC for SF < 2.6
            $authErrorKey = SecurityContextInterface::AUTHENTICATION_ERROR;
            $lastUsernameKey = SecurityContextInterface::LAST_USERNAME;
        }
        // get the error if any (works with forward and redirect -- see below)
        if ($request->attributes->has($authErrorKey)) {
            $error = $request->attributes->get($authErrorKey);
        } elseif (null !== $session && $session->has($authErrorKey)) {
            $error = $session->get($authErrorKey);
            $session->remove($authErrorKey);
        } else {
            $error = null;
        }
        if (!$error instanceof AuthenticationException) {
            $error = null; // The value does not come from the security component.
        }
        // last username entered by the user
        $lastUsername = (null === $session) ? '' : $session->get($lastUsernameKey);
        if ($this->has('security.csrf.token_manager')) {
            $csrfToken = $this->get('security.csrf.token_manager')->getToken('authenticate')->getValue();
        } else {
            // BC for SF < 2.4
            $csrfToken = $this->has('form.csrf_provider')
                ? $this->get('form.csrf_provider')->generateCsrfToken('authenticate')
                : null;
        }
        
        $data = array(
            'last_username' => $lastUsername,
            'error' => $error,
            'csrf_token' => $csrfToken,
            'renderWithoutLayout' => $renderWithoutLayout,
            'redirectURL' => $redirectURL,
        );

        return $this->renderLogin($data);
    }

    /**
     * Renders the login template with the given parameters. Overwrite this function in
     * an extended controller to provide additional data for the login template.
     *
     * @param array $data
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderLogin(array $data)
    {
        $data['facebookLoginURL'] = 'https://www.facebook.com/dialog/oauth?client_id='.$this->app_id.'&redirect_uri='.$data['redirectURL'].'&scope=public_profile,email';
        return $this->render('UKMUserBundle:Security:login.html.twig', $data);
    }

    /**
     * Retur-URL fra Facebook-innlogging.
     * Når en bruker logger inn med facebook er det denne funksjonen som håndterer kommunikasjonen med Facebook og deretter
     * autentisering hos oss (har vi bruker eller må vi opprette ny ++).
     *
     */
    public function fbloginAction() {

        $logger = $this->get('logger');
        $logger->info('UKMSecurityController: fbloginAction()');

        // Burde ligge i constructor
        if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->deltaFBLoginURL = 'https://delta.ukm.dev/app_dev.php/fblogin';
        } 
        else {
            $this->deltaFBLoginURL = 'https://delta.ukm.no/fblogin';
        }
        
        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals();
        $redirectURL = $this->deltaFBLoginURL;
        
        // If mottatt error fra facebook
        $error = $req->query->get('error');
        if ($error == 'access_denied') {
            $this->addFlash('danger', 'Du må godkjenne UKM-appen for å logge inn med Facebook. Vi lover å ikke poste noe på veggen din.');
            $logger->notice("UKMSecurityController::fbloginAction: Fikk access_denied fra Facebook - dette er oftest at brukeren ikke har godkjent UKM-appen.");
            return $this->redirectToRoute('ukm_user_login');
        }

        if ($req->query->get('token')) {
            $logger->info('UKMSecurityController: Got token in query - UKM redirect.');

            $rdirtoken = '?token='.$req->query->get('token');
            $redirectURL = $redirectURL.$rdirtoken;
        }

        $code = $req->query->get('code');
        if ( null == $code ) {
            // Brukeren har ikke blitt sendt tilbake via facebook
            $this->addFlash('danger', "Facebook-innloggingen feilet - prøv igjen, eller kontakt UKM Support");
            $logger->error("UKMSecurityController::fbloginAction: Facebook-innlogging forsøkt uten å gå via facebook - sannsynligvis bare en bot, men sjekk referrer uansett.", array(
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Ikke definert',
                    'redirect_uri' => $redirectURL
                ));
            return $this->redirectToRoute('ukm_user_login');
        }
        // Code is received, which means that the user logged in successfully to facebook.
        $logger->info('UKMSecurityController: Swapping our received code for an access token...');
        // Bytt code for en access-token
        $curl = new UKMCurl();
        $url = 'https://graph.facebook.com/v2.10/oauth/access_token';
        $url .= '?client_id='.$this->getParameter('facebook_client_id');
        $url .= '&redirect_uri='.$redirectURL;
        $url .= '&client_secret='.$this->getParameter('facebook_client_secret');
        $url .= '&code='.$code;
        $curl->timeout(50);
    
        $result = $curl->process($url);
        if(isset($result->error)) {
            $this->addFlash('error', 'Facebook-innloggingen feilet, prøv igjen eller kontakt UKM Support.');
            $logger->error("UKMSecurityController::fbloginAction: Facebook-innlogging feilet på et uventet sted.", array(
                'code' => $code,
                'redirect_uri' => $redirectURL
                ));
            return $this->redirectToRoute('ukm_user_login');
        }

        $token = $result->access_token;

        $logger->info('UKMSecurityController: Got token from Facebook, fetching user data...');

        // Hent brukerdata
        $url = 'https://graph.facebook.com/v2.10/me';
        $url .= '?access_token='.$token;
        $url .= '&fields=id,name,first_name,last_name,email';
        $user = $curl->process($url);
        
        if (isset($user->error)) {
            // Ofte: "This authorization code has been used."
            $this->addFlash('danger', 'Facebook-innloggingen feilet, prøv igjen.');
            $logger->error("UKMSecurityController::fbloginAction: Facebook-innlogging feilet på henting av brukerdata.", array(
                    'error' => $user->error
                ));
            return $this->redirectToRoute('ukm_user_login');
        }
        $logger->debug('UKMSecurityController: No error registered fetching user data.');

        // Sjekk om brukeren er registrert hos oss fra før med facebook-id
        $repo = $this->getDoctrine()->getRepository('UKMUserBundle:User');
        $ukm_user = $repo->findOneBy(array('facebook_id' => $user->id));
        if ($ukm_user) {
            $logger->info('UKMSecurityController: Found user already registered.');

            // Vi har en bruker med denne IDen, logg han/hun inn.
            $request = Request::createFromGlobals();
            $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
            $this->get('security.token_storage')->setToken($usertoken);
            $event = new InteractiveLoginEvent($request, $usertoken);
            $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);

            // Send til rett sted
            $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
            $response = $handler->onAuthenticationSuccess($request, $usertoken);
            return $response;
        }

        $logger->info('UKMSecurityController: No user with that facebook_id registered');
        // Sjekk om brukeren har en konto hos oss med samme e-post-adresse
        // Hvis vi fikk e-post fra facebook
        if (isset($user->email)) {
            $ukm_user = $repo->findOneBy(array('email' => $user->email));
            $logger->info('UKMSecurityController: Checking for matching emails.');
            
            // Vi har en bruker med den e-posten
            if ($ukm_user) {

                $logger->notice('UKMSecurityController: Merging user ata based on matching emails!');    

                // Slå sammen bruker
                $ukm_user->setFacebookId($user->id);
                $userManager = $this->get('fos_user.user_manager');
                $userManager->updateUser($ukm_user);

                $logger->info('UKMSecurityController: Done, logging in user...');
                // Logg inn brukeren
                $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
                $this->get('security.token_storage')->setToken($usertoken);
                $request = Request::createFromGlobals();
                $event = new InteractiveLoginEvent($request, $usertoken);
                $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
            
                // Videresend om man skal videresendes
                $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
                $response = $handler->onAuthenticationSuccess($request, $usertoken);
                
                return $response;
            }
        }

        // Ukjent/ny bruker, send de til registreringsskjemaet først
        require_once('UKM/inc/password.inc.php');

        $logger->notice('UKMSecurityController: No user data found - send user to registration with data from Facebook.');

        $session = $this->getSession();

        // Redirect til ferdigutfylt skjema, som så gjør selve registreringen.
        if(isset($user->email))
            $session->set('email', $user->email);
        if(isset($user->first_name))
            $session->set('first_name', $user->first_name);
        if(isset($user->last_name))
            $session->set('last_name', $user->last_name);
        if(isset($user->id))
            $session->set('facebook_id', $user->id);

        return $this->redirectToRoute('fos_user_registration_register');
    }

    private function getSession() : Session {
        $session = SessionService::getSession();
        return $session;
    }
}
