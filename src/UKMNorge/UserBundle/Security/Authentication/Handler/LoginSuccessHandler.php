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
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $response = null;        
        $this->logger->info('DIPBundle: Authenticated successfully.');

        // If rdirurl is defined
        $rdirurl = $request->request->get('_rdirurl');
        $token = $request->request->get('_rdirtoken');
        
        // Sjekk også session
        $session = $this->container->get('session');
        if($session) {
            if ($session->get('rdirurl')) {
                $rdirurl = $session->get('rdirurl');
                $token = $session->get('rdirtoken');
            }
        }
        
        // If logged in properly.
        if ($this->security->isGranted('ROLE_USER'))
        {   
            // Send godkjenning til tjenesten, om noen.
            $key = null;
            if($rdirurl) {
                $keyRepo = $this->doctrine->getRepository("UKMUserBundle:APIKeys");
                $key = $keyRepo->findOneBy(array('apiKey' => $rdirurl));
                if(!$key) {
                    $errorMsg = 'DIPBundle: Ukjent sted å sende brukerdata til ('.$rdirurl.').';
                    $this->logger->error($errorMsg);
                    die($errorMsg);
                }

                $this->defaultPoster($token, $key);

                // Sjekk om tjenesten har bedt om mer informasjon via scope
                if( $session->get('scope') ) {
                    $this->logger->info('UKMUserBundle: Tjenesten har bedt om mer informasjon via scopes.');
                    $scopes = explode(',', $session->get('scope'));
                    $information_queue = array();
                    foreach( $scopes as $scope) {
                        switch( $scope ) {
                            case 'kommune': 
                                // Hvis vi ikke har registrert informasjon om kommune, legg til i køen av skjema.
                                if (true) {
                                    $information_queue[] = 'kommune';
                                }
                            break;
                            case 'alder':
                                if (true) {
                                    $information_queue[] = 'alder';
                                }
                            break;
                        }
                    }

                    if( !empty($information_queue) ) {
                        $this->logger->info('UKMUserBundle: Noe av informasjonen som er bedt om mangler - sender brukeren til infoQueue.');
                        $session->set('information_queue', $information_queue);
                        // Send brukeren til skjema-håndtering.
                        return new RedirectResponse($this->router->generate('ukm_info_queue'));
                    }
                    else {
                        $this->logger->info('UKMUserBundle: Brukeren har registrert all informasjonen som ble bedt om.');
                    }
                }
            }

            // Gjennomfør redirect til rett tjeneste eller UKMid.
            $redirecter = $this->container->get('ukm_user.redirect');
            return $redirecter->doRedirect();
        }

        // IKKE LOGGET INN - SKJER DETTE NOENSINNE?
        $this->logger->critical("UKMUserBundle: En bruker har kommet til LoginSuccessHandler uten å ha minimum rollen ROLE_USER - dette er en bug som ikke skal gå an. Stacktrace: ".var_export(debug_backtrace(), true) );
        mail('support@ukm.no','UKMUserBundle: En bruker har kommet til LoginSuccessHandler uten å ha rollen ROLE_USER', 'Dette skal ikke skje, og er en systemfeil. Stacktrace: '. var_export(debug_backtrace(), true) );
        throw new Exception("En systemfeil har oppstått - du er logget inn, men ikke autorisert. Kontakt UKM Support.");
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