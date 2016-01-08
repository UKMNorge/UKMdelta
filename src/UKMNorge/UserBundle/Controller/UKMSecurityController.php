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
	
	public function loginAction(Request $request)
    {	
        /** @var $session \Symfony\Component\HttpFoundation\Session\Session */
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
        // Sjekk om dette er en redirect-forespørsel
        if ($request->query->get('rdirurl') == 'ambassador') {
        	$data['rdirurl'] = 'ambassador';
        	$data['rdirtoken'] = $request->query->get('token');
        	
            // If already logged in:
            $securityContext = $this->get('security.authorization_checker');
            if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED') || $securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
                
                $usertoken = $this->get('security.token_storage')->getToken();    
                // Get the LoginSuccessHandler, which will redirect as proper
                $request = Request::createFromGlobals();
                // $data = array();
                // $data['_rdirurl'] = 'ambassador';
                // $request = new Request(array(), $data);
                #$request->request->set('_rdirurl', 'ambassador');
                $all = $request->request->all(); 
                $all['_rdirurl'] = $data['rdirurl']; 
                $all['_rdirtoken'] = $data['rdirtoken'];
                $request->request->replace($all);
                $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
                #var_dump($request);

                $response = $handler->onAuthenticationSuccess($request, $usertoken);
                var_dump($response);
                return $response;
            }
            // $router = $this->get('router');

    		// if ($securityContext->isGranted('ROLE_USER')) {
    		// 	return new ReirectResponse($ambURL, 302);
    		// }
        }

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
        require_once('UKM/curl.class.php');
        $req = Request::createFromGlobals(); 
        $redirectURL = 'http://delta.'. $this->getParameter('UKM_HOSTNAME') . '/web/app_dev.php/fblogin';

        $code = $req->query->get('code');
        // Code is received, which means that the user logged in successfully.
        //var_dump($code);

        // Bytt code for en access-token
        $curl = new UKMCurl();
        $url = 'https://graph.facebook.com/v2.3/oauth/access_token';
        $url .= '?client_id='.$this->getParameter('facebook_client_id');
        $url .= '&redirect_uri='.$redirectURL;
        $url .= '&client_secret='.$this->getParameter('facebook_client_secret');
        $url .= '&code='.$code;

        $result = $curl->process($url);
        if(isset($result->error)) {        
            var_dump($result);
            die();
        }
        //var_dump($result);
        $token = $result->access_token;

        // Verify token?


        // Hent brukerdata
        $url = 'https://graph.facebook.com/me';
        $url .= '?access_token='.$token;
        $user = $curl->process($url);

        if (isset($user->error)) {
            var_dump($user);
            die();
        }
        //var_dump($user);
        
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
            // Redirect!
            return $this->redirectToRoute('ukm_delta_ukmid_homepage');
        }

        // Sjekk om brukeren har en konto hos oss med samme e-post-adresse
        // Hvis vi fikk e-post fra facebook
        if (isset($user->email)) {
            $ukm_user = $repo->findOneBy(array('email' => $user->email));
            // Vi har en bruker med den e-posten
            if ($ukm_user) {
                // Slå sammen bruker
                die('TODO: Slå sammen brukere');

                // Vi har en bruker, logg han/hun inn.
                $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
                $this->get('security.token_storage')->setToken($usertoken);
                $request = $this->get('request');
                $event = new InteractiveLoginEvent($request, $usertoken);
                $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
                // Redirect!
                return $this->redirectToRoute('ukm_delta_ukmid_homepage');
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
        
        #### OLD! ####
        // Register user here
        $ukm_user = new User();
        $ukm_user->setFirstName($user->first_name);
        $ukm_user->setLastName($user->last_name);
        $ukm_user->setFacebookId($user->id);
        $ukm_user->setEmail($user->email);
        $ukm_user->setPassword(UKM_ordpass(true));
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($ukm_user);
        $em->flush();

        // Dispatch registration confirmed event

        var_dump($ukm_user);
        // Logg inn brukeren, men redirect til telefonnummer-spørsmålet?
        $usertoken = new UsernamePasswordToken($ukm_user, $ukm_user->getPassword(), "ukm_delta_wall", $ukm_user->getRoles());
        $this->get('security.token_storage')->setToken($usertoken);
        $request = $this->get('request');
        $event = new InteractiveLoginEvent($request, $usertoken);
        $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
        // Redirect til mer info-side

        // Redirect til ukmid om vi har all info
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }

}