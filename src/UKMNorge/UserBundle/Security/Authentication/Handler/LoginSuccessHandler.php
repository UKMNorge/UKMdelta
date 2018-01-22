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

        // If rdirurl is defined - brukes i noen interne ting (login via facebook etc).
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
            $user = $this->ukm_user->getCurrentUser();
            if($rdirurl) {
                // Sjekk om tjenesten har bedt om mer informasjon via scope, og bygg i så fall listen over info vi mangler.
                if( $session->get('scope') ) {
                    $this->logger->info('UKMUserBundle: Tjenesten har bedt om mer informasjon via scopes.');
                    $scopes = explode(',', $session->get('scope'));
                    $information_queue = array();
                    foreach( $scopes as $scope) {
                        switch( $scope ) {
                            case 'kommune': 
                                if( null == $user->getKommuneId() ) {
                                    $information_queue[] = 'kommune';
                                }
                            break;
                            case 'alder':
                                if (true) {
                                    $information_queue[] = 'alder';
                                }
                            case 'facebook': {
                                if( null == $user->getFacebookId() || !is_numeric($user->getFacebookId()) ) {
                                    $information_queue[] = 'facebook';
                                }
                            }
                            break;
                            // Ukjent scope - kast en Exception.
                            // Stopper prosessen fordi noen tjenester kan kreve informasjonen for å fungere, 
                            // og det er bedre å få vite det ved innloggingen enn ved at systemet ikke funker på en rar måte.
                            default:
                                $this->logger->error( "UKMUserBundle: Tjenesten du prøvde å logge inn på har bedt om en ukjent tilgang: " . $scope );
                                throw new Exception( "UKMUserBundle: Tjenesten du prøvde å logge inn på har bedt om en ukjent tilgang. Dette er en systemfeil, ta kontakt med UKM Support." );
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
            $this->logger->debug("LoginSuccessHandler: Logged in, redirecting...");
            $redirecter = $this->container->get('ukm_user.redirect');
            return $redirecter->doRedirect();
        }

        // IKKE LOGGET INN - SKJER DETTE NOENSINNE?
        $this->logger->critical("UKMUserBundle: En bruker har kommet til LoginSuccessHandler uten å ha minimum rollen ROLE_USER - dette er en bug som ikke skal gå an. Stacktrace: ".var_export(debug_backtrace(), true) );
        mail('support@ukm.no','UKMUserBundle: En bruker har kommet til LoginSuccessHandler uten å ha rollen ROLE_USER', 'Dette skal ikke skje, og er en systemfeil. Stacktrace: '. var_export(debug_backtrace(), true) );
        throw new Exception("En systemfeil har oppstått - du er logget inn, men ikke autorisert. Kontakt UKM Support.");
    }

}