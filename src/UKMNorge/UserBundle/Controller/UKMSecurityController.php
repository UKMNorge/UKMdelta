<?php

namespace UKMNorge\UserBundle\Controller;


/* FROM PARENT */
	use Symfony\Bundle\FrameworkBundle\Controller\Controller;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\Security\Core\Security;
	use Symfony\Component\Security\Core\SecurityContextInterface;
	use Symfony\Component\Security\Core\Exception\AuthenticationException;
/* E.O FROM PARENT */
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
        	
      //   	// If logged in properly!
    		// $securityContext = $this->get('security.context');
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
        var_dump($result);
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
        var_dump($user);

        // Sjekk om brukeren er registrert hos oss fra før
        $repo = $this->getDoctrine()->getRepository('UKMUserBundle:User');
        //var_dump($repo);
        $existingUser = $repo->findOneBy(array('facebook_id' => $user->id));
        if ($existingUser) {
            // Vi har en bruker med denne IDen, logg han inn og redirect.

            var_dump($existingUser);
            die();
        }
        // Register user here
        $ukm_user = new User();
        $ukm_user->setFirstName($user->first_name);
        $ukm_user->setLastName($user->last_name);
        $ukm_user->setFacebookId($user->id);
        $ukm_user->setEmail($user->email);
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($ukm_user);
        $em->flush();

        var_dump($ukm_user);
        // Logg inn brukeren, men redirect til telefonnummer-spørsmålet
        
        // Redirect til ukmid om vi har all info
        die();
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }

}