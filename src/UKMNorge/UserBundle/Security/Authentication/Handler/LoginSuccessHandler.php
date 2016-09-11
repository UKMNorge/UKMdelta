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
use DateTime;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{

    protected $router;
    protected $security;
    private $ambURL;
    private $ambDipURL;
    private $rsvpURL;
    private $rsvpDipURL;
    // var $ambURL = 'http://ambassador.ukm.no/dip/login';

    public function __construct(Router $router, SecurityContext $security, $doctrine, $ukm_user, $container)
    {
        $this->router = $router;
        $this->security = $security;
        $this->doctrine = $doctrine;
        $this->ukm_user = $ukm_user;
        $this->container = $container;
        $this->logger = $container->get('logger');


        if ( $this->container->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.dev/app_dev.php/dip/receive/';
            $this->rsvpURL = 'http://rsvp.ukm.dev/web/app_dev.php/dip/login';
            $this->rsvpDipURL = 'http://rsvp.ukm.dev/web/app_dev.php/dip/receive/';
            $this->testURL = 'http://test.ukm.dev/app_dev.php/login/';
            $this->testDipURL = 'http://test.ukm.dev/app_dev.php/receive/';
        } 
        else {
            $this->ambURL = 'http://ambassador.ukm.no/dip/login';
            $this->ambDipURL = 'http://ambassador.ukm.no/dip/receive/';
            $this->rsvpURL = 'http://rsvp.ukm.no/dip/login';
            $this->rsvpDipURL = 'http://rsvp.ukm.no/dip/receive/';
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {

        $response = null;
        
        $this->logger->info('DIPBundle: Authenticated successfully.');
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

        $key = null;
        if($rdirurl && $rdirurl != 'ambassador' && $rdirurl != 'rsvp') {
            $keyRepo = $this->doctrine->getRepository("UKMUserBundle:APIKeys");
            $key = $keyRepo->findOneBy(array('apiKey' => $rdirurl));
            if(!$key) {
                $errorMsg = 'DIPBundle: Ukjent sted å sende brukerdata til ('.$rdirurl.').';
                $this->logger->error($errorMsg);
                die($errorMsg);
            }
        }
        // throw new Exception('Sjekk session!');
        // var_dump($request);
        // die();
        // If logged in, or something?
        #var_dump($this->security->isGranted('ROLE_USER'));
        if ($this->security->isGranted('ROLE_USER'))
        {
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
                    $rdirurl = $this->ambURL;             
                    break;
                case 'rsvp':
                    $user = $this->ukm_user->getCurrentUser();
                    if (!$user->getFacebookId()) {
                        // Hvis brukeren ikke har koblet til facebook
                        // Redirect til facebook-connect m/ redirect?
                        $r = new RedirectResponse($this->router->generate('ukm_fb_connect'));
                        return $r;
                        // throw new Exception('Du må koble til med facebook for å åpne ambassadør-siden!', 20007);
                    }
                    // Sett token i databasen
                    $this->rsvp($token);
                    // Sett reell redirectURL
                    $rdirurl = $this->rsvpURL;             
                    break;
                case 'test':
                    $user = $this->ukm_user->getCurrentUser();
                    if (!$user->getFacebookId()) {
                        // Hvis brukeren ikke har koblet til facebook
                        // Redirect til facebook-connect m/ redirect?
                        $r = new RedirectResponse($this->router->generate('ukm_fb_connect'));
                        return $r;
                        // throw new Exception('Du må koble til med facebook for å åpne ambassadør-siden!', 20007);
                    }
                    // Sett token i databasen
                    $this->test($token);
                    // Sett reell redirectURL
                    $rdirurl = $this->testURL;             
                    break;
                default: 
                    if($key) {
                        $this->defaultPoster($token, $key);
                        $rdirurl = $key->getApiReturnURL();
                    }
                    else 
                        $rdirurl = $this->router->generate('ukm_delta_ukmid_homepage');

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
        
        $this->logger->info('DIPBundle: ambassador');

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
        $res = $curl->process($this->ambDipURL);
    }

    private function rsvp($token) {
        require_once('UKM/curl.class.php');
        
        $this->logger->info('DIPBundle: rsvp');
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
        $res = $curl->process($this->rsvpDipURL);
    }

    private function test($token) {
        require_once('UKM/curl.class.php');
        
        $this->logger->info('DIPBundle: test');
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
        $res = $curl->process($this->testDipURL);
        $this->logger->info('DIPBundle: Curl-respons: '.$res);
    }

    private function defaultPoster($token, $api_key) {
        require_once('UKM/curl.class.php');
        
        $this->logger->info('DIPBundle: Selecting user-data to POST.');

        $user = $this->ukm_user->getCurrentUser();

        // Set more token-info
        $repo = $this->doctrine->getRepository("UKMUserBundle:DipToken");
        $dbToken = $repo->findOneBy(array('token' => $token));
        $dbToken->setTimeUsed(new DateTime());
        $dbToken->setUserId($user->getId());

        $this->doctrine->getManager()->persist($dbToken);
        $this->doctrine->getManager()->flush();

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
        // Send brukerinfo til gitt sted

        $this->logger->info('DIPBundle: Curling user-data to '. $api_key->getApiKey() . ' ('.$api_key->getApiTokenURL() .')');
        $curl = new UKMCurl();
        $curl->post(array('json' => $json));
        // Res skal være et JSON-objekt.
        $res = $curl->process($api_key->getApiTokenURL());
        $this->logger->info('DIPBundle: Curl-respons: '.var_export($res, true));
        if(!is_object($res)) {
            // TODO: Error
            $this->logger->error('DIPBundle: Tjenesten '.$api_key->getApiKey() .' svarte ikke med en godkjent status!');
            $errorMsg = 'Tjenesten du prøvde å logge inn på klarte ikke å ta i mot brukerinformasjonen din. Dette er en systemfeil, ta kontakt med UKM Support hvis feilen fortsetter.';
            throw new Exception($errorMsg);
        }
        if(!$res->success) {
            // TODO: Error
            $this->logger->error('DIPBundle: Tjenesten '.$api_key->getApiKey() .' svarte med success == false!');
            $errorMsg = 'Tjenesten du prøvde å logge inn på klarte ikke å ta i mot brukerinformasjonen din. Dette er en systemfeil, ta kontakt med UKM Support hvis feilen fortsetter.';
            throw new Exception($errorMsg);
        }
    }

}