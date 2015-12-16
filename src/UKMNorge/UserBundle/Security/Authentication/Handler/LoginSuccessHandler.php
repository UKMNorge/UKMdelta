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

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{

    protected $router;
    protected $security;
    var $ambURL = 'http://ambassador.ukm.dev/web/app_dev.php/dip/login';

    public function __construct(Router $router, SecurityContext $security, $doctrine, $ukm_user)
    {
        $this->router = $router;
        $this->security = $security;
        $this->doctrine = $doctrine;
        $this->ukm_user = $ukm_user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {

        $response = null;

        // If rdirurl is defined
        $rdirurl = $request->request->get('_rdirurl');
        if ($this->security->isGranted('ROLE_USER'))
        {
            #var_dump($rdirurl);
            switch ($rdirurl) {
                case 'ambassador': 
                    // Hent token
                    $token = $request->request->get('_rdirtoken');
                    // Sett token i databasen
                    $this->ambassador($token);
                    // Sett reell redirectURL
                    $rdirurl = 'http://ambassador.ukm.dev/app_dev.php/dip/login';

                    break;
                default: $rdirurl = $this->router->generate('ukm_delta_ukmid_homepage');
            }
            #var_dump($rdirurl);
            #var_dump($request);
            #die();

        
            // Default response er redirect til UKMID
            $response = new RedirectResponse($rdirurl);
            #$response = new RedirectResponse($this->router->generate('frontend'));
        } 



        return $response;
    }

    private function ambassador($token) {
        require_once('UKM/curl.class.php');
        $ambURL = 'http://ambassador.ukm.dev/app_dev.php/dip/receive/';

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

        $json = json_encode($json);
        #var_dump($json);
        // Send brukerinfo til ambassadør
        $curl = new UKMCurl();
        $curl->post(array('json' => $json));
        $res = $curl->process($ambURL);
        #var_dump($res);
        #echo 'DB-info: ';
        //var_dump($repo);
        #var_dump($dbToken);

        #echo '<br>';
    }

}