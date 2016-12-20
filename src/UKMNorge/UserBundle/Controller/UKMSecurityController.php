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

use FOS\UserBundle\Controller\SecurityController as BaseController;
use UKMNorge\UserBundle\UKMUserEvents;
use UKMNorge\UserBundle\Entity\User;
use UKMCurl;

class UKMSecurityController extends BaseController {
    
	public function loginAction(Request $request, $renderWithoutLayout=false)
    {	

        if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.dev/app_dev.php/dip/receive/';
            $this->deltaFBLoginURL = 'http://delta.ukm.dev/web/app_dev.php/fblogin';
        } 
        else {
            $this->ambURL = 'http://ambassador.ukm.no/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.no/dip/receive/';
            $this->deltaFBLoginURL = 'http://delta.ukm.no/fblogin';
        }

        // Er dette en redirect-forespørsel?
        $rdirurl = '';
        $rdirtoken = '';
        if ($request->query->get('rdirurl')) {
            $rdirurl = $request->query->get('rdirurl');
            $rdirtoken = '?token='.$request->query->get('token');

            // Lagre i session også
            $request->getSession()->set('rdirurl', $rdirurl);
            $request->getSession()->set('rdirtoken', $request->query->get('token'));
        }

        // Ber tjenesten om å få mer informasjon tilbake?
        if( $request->query->get('scope') ) {
            $request->getSession()->set('scope', $request->query->get('scope'));
        } 
        else {
            // Vask session for scope dersom det ikke er satt i request.
            $request->getSession()->remove('scope');
            $request->getSession()->remove('infoQueue');
        }
        
        $app_id = $this->getParameter('facebook_client_id');
        
        $redirectURL = $this->deltaFBLoginURL.$rdirtoken;
        
        $session = $request->getSession();
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
        );
        // Gjøres tidligere
        $data['rdirurl'] = $rdirurl;
        $data['rdirtoken'] = $rdirtoken;
        // Sjekk om dette er en redirect-forespørsel
        if ($rdirurl) {
            // If already logged in:
            $securityContext = $this->get('security.authorization_checker');
            if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED') || $securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
                
                $usertoken = $this->get('security.token_storage')->getToken();    
                // Get the LoginSuccessHandler, which will redirect as proper
                $request = Request::createFromGlobals();
                $all = $request->request->all(); 
                $all['_rdirurl'] = $data['rdirurl']; 
                $all['_rdirtoken'] = $data['rdirtoken'];
                $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');

                $response = $handler->onAuthenticationSuccess($request, $usertoken);
                return $response;
            }
        }

        $data['facebookLoginURL'] = 'https://www.facebook.com/dialog/oauth?client_id='.$app_id.'&redirect_uri='.$redirectURL.'&scope=public_profile,email';
		$data['renderWithoutLayout'] = $renderWithoutLayout;
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
        return $this->render('UKMUserBundle:Security:login.html.twig', $data);
    }

    public function fbloginAction() {

        if ( $this->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.dev/app_dev.php/dip/receive/';
            $this->deltaFBLoginURL = 'http://delta.ukm.dev/web/app_dev.php/fblogin';
        } 
        else {
            $this->ambURL = 'http://ambassador.ukm.no/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.no/dip/receive/';
            $this->deltaFBLoginURL = 'http://delta.ukm.no/fblogin';
        }
        
        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        
        // If mottatt error fra facebook
        $error = $req->query->get('error');
        if ($error == 'access_denied') {
            $this->addFlash('danger', 'Du må godkjenne UKM-appen for å logge inn med Facebook. Vi lover å ikke poste noe på veggen din.');
            return $this->redirectToRoute('ukm_user_login');
        }

        $redirectURL = $this->deltaFBLoginURL;

        if ($req->query->get('token')) {
            $rdirtoken = '?token='.$req->query->get('token');
            $redirectURL = $redirectURL.$rdirtoken;
        }

        $code = $req->query->get('code');
        // Code is received, which means that the user logged in successfully to facebook.
        // Bytt code for en access-token
        $curl = new UKMCurl();
        $url = 'https://graph.facebook.com/v2.3/oauth/access_token';
        $url .= '?client_id='.$this->getParameter('facebook_client_id');
        $url .= '&redirect_uri='.$redirectURL;
        $url .= '&client_secret='.$this->getParameter('facebook_client_secret');
        $url .= '&code='.$code;
        $curl->timeout(50);
    
        $result = $curl->process($url);
        if(isset($result->error)) {   
            $this->addFlash('Facebook-innloggingen feilet, prøv igjen.');
            return $this->redirectToRoute('ukm_user_login');     
        }

        $token = $result->access_token;

        // Hent brukerdata
        $url = 'https://graph.facebook.com/me';
        $url .= '?access_token='.$token;
        $user = $curl->process($url);

        if (isset($user->error)) {
            // Ofte: "This authorization code has been used."
            $this->addFlash('danger', 'Facebook-innloggingen feilet, prøv igjen.');
            return $this->redirectToRoute('ukm_user_login');

        }
        
        // Sjekk om brukeren er registrert hos oss fra før med facebook-id
        $repo = $this->getDoctrine()->getRepository('UKMUserBundle:User');
        $ukm_user = $repo->findOneBy(array('facebook_id' => $user->id));
        if ($ukm_user) {
            // Vi har en bruker med denne IDen, logg han/hun inn.
            $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
            $this->get('security.token_storage')->setToken($usertoken);

            $request = $this->get('request');
            $event = new InteractiveLoginEvent($request, $usertoken);
            $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
            // Fyll inn rdirtoken og rdirurl om de er satt
            if ($rdirtoken = $request->query->get('token')) {
                // Look up token
                $tokenRepo = $this->getDoctrine()->getRepository('UKMUserBundle:DipToken');
                $token = $tokenRepo->findOneBy(array('token' => $rdirtoken));
                if($token) {
                    $all['_rdirurl'] = $token->getLocation(); 
                    $all['_rdirtoken'] = $rdirtoken;
                    $request->request->replace($all);
                    $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');

                    $response = $handler->onAuthenticationSuccess($request, $usertoken);
                    return $response;
                }
            }
            
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        // Sjekk om brukeren har en konto hos oss med samme e-post-adresse
        // Hvis vi fikk e-post fra facebook
        if (isset($user->email)) {
            $ukm_user = $repo->findOneBy(array('email' => $user->email));
            // Vi har en bruker med den e-posten
            if ($ukm_user) {
                // Slå sammen bruker
                $ukm_user->setFacebookId($user->id);
                $userManager = $this->get('fos_user.user_manager');
                $userManager->updateUser($ukm_user);

                // Logg inn brukeren
                $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
                $this->get('security.token_storage')->setToken($usertoken);
                $request = $this->get('request');
                $event = new InteractiveLoginEvent($request, $usertoken);
                $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
            
                // Videresend om man skal videresendes
                $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
                $response = $handler->onAuthenticationSuccess($request, $usertoken);
                
                return $response;
            }
        }
        require_once('UKM/inc/password.inc.php');

        // TODO: Redirect til ferdigutfylt skjema, som så gjør selve registreringen.
        // Da må facebook-data i session / view_data
        if(isset($user->email))
            $this->get('session')->set('email', $user->email);
        if(isset($user->first_name))
            $this->get('session')->set('first_name', $user->first_name);
        if(isset($user->last_name))
            $this->get('session')->set('last_name', $user->last_name);
        if(isset($user->id))
            $this->get('session')->set('facebook_id', $user->id);

        return $this->redirectToRoute('fos_user_registration_register');
    }

  

}