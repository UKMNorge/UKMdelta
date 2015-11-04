<?php

namespace UKMNorge\UserBundle\EventListener;

use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use FOS\UserBundle\FOSUserEvents;
use UKMNorge\UserBundle\UKMUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Exception;

class RegistrationListener implements EventSubscriberInterface
{
    public function __construct($container)
    {
		$this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return array(FOSUserEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize',
        			 FOSUserEvents::REGISTRATION_SUCCESS => 'onRegistrationSuccess',
					 UKMUserEvents::REGISTRATION_ERROR => 'onRegistrationError',
					 FOSUserEvents::REGISTRATION_CONFIRM => 'onRegistrationConfirm',
					 FOSUserEvents::RESETTING_RESET_SUCCESS => 'onResetComplete',
					);
    }


	/**
	 * onRegistrationConfirm
	 *
	 * User is confirmed and activated. Send password by SMS before
	 * completing registration process
	 *
	 **/
	public function onRegistrationConfirm(GetResponseUserEvent $event) {
		require_once('UKM/inc/password.inc.php');
		
		$tokenGenerator = $this->container->get('fos_user.util.token_generator');
		$password = UKM_ordpass(true); // true gives numbers before words
		$event->getUser()->setPlainPassword( $password );

   		$text = $this->container->getParameter('ukmdelta.sms.password.created');
		
		$UKMSMS = $this->container->get('ukmsms');
        try {
	        $UKMSMS->sendSMS( $event->getUser()->getPhone(), str_replace('#code', $password, $text) );
	    } catch( Exception $e ) {
		    $this->container->get('session')->getFlashBag()->add('error', 'Kunne ikke sende engangskode på SMS ('.$e->getMessage().')');
	    }

	}

	/**
	 * onRegistrationSuccess
	 *
	 * User is generated and stored in database
	 * Generate and send SMS with confirmation code to user
	 *
	 **/
    public function onRegistrationError(GetResponseUserEvent $event)
    {
	    $request = $event->getRequest();
	    $email = $request->request->get('fos_user_registration_form')['email'];

	    if( empty( $email ) ) {
		    return;
	    }
	    
        $userManager = $this->container->get('fos_user.user_manager');
	    $user = $userManager->findUserByEmail( $email );
		if( get_class( $user ) == 'UKMNorge\UserBundle\Entity\User' && !$user->isEnabled() ) {
			$this->container->get('session')->set('fos_user_send_confirmation_email/email', $email);
			$url = $this->container->get('router')->generate('ukm_user_registration_check_sms', array('sent_before'=>true));
			
			$event->setResponse( new RedirectResponse( $url ) );
		}
	}
	
	/**
	 * onRegistrationSuccess
	 *
	 * User is generated and stored in database
	 * Generate and send SMS with confirmation code to user
	 *
	 **/
    public function onRegistrationSuccess($event)
    {	    
		$url = $this->container->get('router')->generate('ukm_user_registration_check_sms');
		$event->setResponse(new RedirectResponse($url));
	}
	
    /**
     * onRegistrationInitialize
     * Generate and set SMS confirmation code (should be tokenized)
     * Sets a random password to prohibit logon
     **/
    public function onRegistrationInitialize(GetResponseUserEvent $event)
    {
		$user = $event->getUser();
		
		$tokenGenerator = $this->container->get('fos_user.util.token_generator');
		$password = substr($tokenGenerator->generateToken(), 0, 8); // 8 chars		
		$password = '1234';
		
		$smscode = (int) ( rand(10,99).''.rand(10,99) ); #.''.rand(10,99) );
		
		$user->setPlainPassword( $password );
		$user->setSmsValidationCode( $smscode );
    }
    
    public function onResetComplete(FormEvent $event) {
		$url = $this->container->get('router')->generate('ukm_delta_ukmid_homepage');
		$event->setResponse( new RedirectResponse( $url ) );
    }
}