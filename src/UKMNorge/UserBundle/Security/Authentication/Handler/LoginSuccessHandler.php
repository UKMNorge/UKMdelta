<?php 
namespace UKMNorge\UserBundle\Security\Authentication\Handler;

use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;

use UKMCurl;
use Exception;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{

    protected $router;
    protected $security;
    // var $ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/login';
    var $ambURL = 'http://ambassador.ukm.no/dip/login';

    public function __construct(Router $router, SecurityContext $security, $doctrine, $ukm_user, $container)
    {
        $this->router = $router;
        $this->security = $security;
        $this->doctrine = $doctrine;
        $this->ukm_user = $ukm_user;
        $this->container = $container;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {

        $response = null;
        
        #var_dump($request);
        // If rdirurl is defined
        $rdirurl = $request->request->get('_rdirurl');
        $token = $request->request->get('_rdirtoken');
        // Sjekk også session
        //$session = $request->getSession();
        $session = $this->container->get('session');
        // var_dump((array)$session_two);
        // var_dump((array)$session);
        // throw new Exception('Staaahp');
        if($session) {
            // die("if(session) works");
            // var_dump($session->get('rdirurl'));
            
            
            if ($session->get('rdirurl')) {
                $rdirurl = $session->get('rdirurl');
                $token = $session->get('rdirtoken');

                // var_dump($rdirurl);
                // die();
            }
        }
        // throw new Exception('Sjekk session!');
        // var_dump($request);
        // die();
        // If logged in, or something?
        #var_dump($this->security->isGranted('ROLE_USER'));
        if ($this->security->isGranted('ROLE_USER'))
        {
            // throw new Exception('Innlogget.');
            #var_dump($rdirurl);
            switch ($rdirurl) {
                case 'ambassador': 
                    
                    // throw new Exception('ambassador');
                    // Sjekk at personen har alt som kreves for ambassadør? 
                    // Altså facebook_id
                    $user = $this->ukm_user->getCurrentUser();
                    if (!$user->getFacebookId()) {
                        // Hvis brukeren ikke har koblet til facebook
                        // Redirect til facebook-connect m/ redirect?
                        $r = new RedirectResponse($this->router->generate('ukm_fb_connect'));
                        return $r;
                        // throw new Exception('Du må koble til med facebook for å åpne ambassadør-siden!', 20007);
                    }
                    // Sett token i databasen
                    $this->ambassador($token);
                    // Sett reell redirectURL
                    $rdirurl = 'http://ambassador.ukm.no/dip/login';
                    
                    break;
                default: $rdirurl = $this->router->generate('ukm_delta_ukmid_homepage');
            }
            #var_dump($rdirurl);
            #var_dump($request);
            #die();

            // Fjern redirect-session-variabler
            if($session) {
                $session->remove('rdirurl');
                $session->remove('rdirtoken');
            }
            // Default response er redirect til UKMID
            $response = new RedirectResponse($rdirurl);
            #$response = new RedirectResponse($this->router->generate('frontend'));
        } 



        return $response;
    }

    private function ambassador($token) {
        require_once('UKM/curl.class.php');
        $ambURL = 'http://ambassador.ukm.no/dip/receive/';

        #$repo = $this->getDoctrine()->getRepository('UKMDipBundle:Token');
        $repo = $this->doctrine->getRepository("UKMUserBundle:DipToken");
        $dbToken = $repo->findOneBy(array('token' => $token));

        $user = $this->ukm_user->getCurrentUser();
        // Encode brukerdata og token til JSON-objekt
        $json = array();
        $json['token'] = $token;

        $json['delta_id'] = $user->getId();
        $json['email'] = $user->getEmail();
        $json['phone'] = $user->getPhone();
        $json['address'] = $user->getAddress();
        $json['post_number'] = $user->getPostNumber();
        $json['post_place'] = $user->getPostPlace();
        $json['birthdate'] = $user->getBirthdate();
        $json['facebook_id'] = $user->getFacebookId();
        $json['facebook_id_unencrypted'] = $user->getFacebookIdUnencrypted();
        $json['facebook_access_token'] = $user->getFacebookAccessToken();
        $json['first_name'] = $user->getFirstName();
        $json['last_name'] = $user->getLastName();

        $json = json_encode($json);
        #var_dump($json);
        // Send brukerinfo til ambassadør
        $curl = new UKMCurl();
        $curl->post(array('json' => $json));
        $res = $curl->process($ambURL);
        
        #echo 'DB-info: ';
        //var_dump($repo);
        #var_dump($dbToken);

        #echo '<br>';
    }

}