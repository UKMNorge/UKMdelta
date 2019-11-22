<?php

namespace UKMNorge\UserBundle\Controller;
/* FROM PARENT */
	use FOS\UserBundle\FOSUserEvents;
	use FOS\UserBundle\Event\FormEvent;
	use FOS\UserBundle\Event\GetResponseUserEvent;
	use FOS\UserBundle\Event\FilterUserResponseEvent;
	use Symfony\Bundle\FrameworkBundle\Controller\Controller;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\RedirectResponse;
	use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
	use Symfony\Component\Security\Core\Exception\AccessDeniedException;
	use FOS\UserBundle\Model\UserInterface;
/* E.O FROM PARENT */

use UKMmail;
use UKMNorge\UserBundle\UKMUserEvents;
use UKMNorge\UserBundle\Entity\SMSValidation;
use UKMNorge\UserBundle\Entity\Repository\SMSValidationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Exception;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use FOS\UserBundle\Controller\RegistrationController as BaseController;

class RegistrationController extends BaseController
{

	public function __construct() {

	}
	
    public function registerAction(Request $request)
    {
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.registration.form.factory');
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');
        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user = $userManager->createUser();
        $user->setEnabled(true);

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $formFactory->createForm();
        $form->setData($user);
        // Sett data fra facebook i form, om vi har mottatt de.
        if ($this->get('session')->get('email'))
        	$form->get('email')->setData($this->get('session')->get('email'));
        if ($this->get('session')->get('first_name'))
        	$form->get('first_name')->setData($this->get('session')->get('first_name'));
        if ($this->get('session')->get('last_name'))
        	$form->get('last_name')->setData($this->get('session')->get('last_name'));
        if ($this->get('session')->get('facebook_id'))
        	$form->add('facebook_id', 'hidden', array('data' => $this->get('session')->get('facebook_id')));
        $form->handleRequest($request);
        
		// CASE 1: Form submitted, valid and user creation is possible
        if ($form->isValid()) {
            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $userManager->updateUser($user);

			$response = $event->getResponse();
            if (null === $response) {
                $url = $this->generateUrl('ukm_delta_ukmid_homepage');
                $response = new RedirectResponse($url);
            }

            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

            return $response;
        }
        
        // CASE 2: Form submitted, invalid data entered. Handle it
	    if( $request->isMethod('POST') ) {
		    $errors = $this->getErrorMessages( $form );

		    // CASE 2.1 Mobilnummer eksisterer allerede
		    if( isset( $errors['phone'] ) && is_array( $errors['phone'] ) ) {
			    foreach( $errors['phone'] as $phone_error ) {
				    if( $phone_error == 'ukm_user.phone.already_used') {
				    	$phone = $form->get('phone')->getData();
				    	// Sjekk om dette er en som kommer via facebook:
				    	if($this->get('session')->get('facebook_id')) {
				    		// Finn brukeren:
				    		$userRepo = $this->getDoctrine()->getRepository('UKMUserBundle:User');
				    		$user = $userRepo->findOneBy(array('phone' => $phone));
				    		$user->setFacebookId($this->get('session')->get('facebook_id'));
				    		$userManager = $this->get('fos_user.user_manager');
				    		$userManager->updateUser($user);

				    		// Logg inn brukeren
				    		$request = $this->get('request');
				    		$usertoken = new UsernamePasswordToken($user, $user->getPassword(), "ukm_delta_wall", $user->getRoles());
				            $this->get('security.token_storage')->setToken($usertoken);
				            $event = new InteractiveLoginEvent($request, $usertoken);
				            $this->get("event_dispatcher")->dispatch('security.interactive_login', $event);
				    		// Redirect etc
				    		
                    		$handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');

		                    $response = $handler->onAuthenticationSuccess($request, $usertoken);
		                    return $response;
				    	}


					    
					    return $this->redirectToRoute('ukm_user_registration_existing_phone', array('phone' => $phone));
				    }
			    }
		    }

		    if (isset($errors['email']) && is_array($errors['email'])) {
		    	foreach( $errors['email'] as $email_key => $email_error ) {
		    		if( $email_error == 'fos_user.email.already_used') {
		    			$email = $form->get('email')->getData();
		    			
		    			// Sjekk om dette er en som kommer via facebook:
		    			if($this->get('session')->get('facebook_id')) {
				    		$errors['email'][$email_key] = 'fos_user.email.already_used_fb';
				    		// Redirect til et nytt view!
				    		return $this->redirectToRoute('ukm_user_registration_existing_email', array('email' => $email));
				    	}
		    		}
		    	}

		    	// Redirect til register-form igjen?
		    	return $this->render('FOSUserBundle:Registration:register.html.twig', 
									array('form' => $form->createView(), 'phoneAlreadyRegistered'=>false, 'errors' => $errors)
								);
		    }
		    
		    // CASE 2.2 CSRF-token error
		}
		
		// CASE 3: Form not submitted at all
		return $this->render('FOSUserBundle:Registration:register.html.twig', 
									array('form' => $form->createView(), 'phoneAlreadyRegistered'=>false)
								);
    }	

    // FROM: http://stackoverflow.com/a/17428869
	private function getErrorMessages(\Symfony\Component\Form\Form $form) {
	    $errors = array();
	
	    foreach ($form->getErrors() as $key => $error) {
	        if ($form->isRoot()) {
	            $errors['#'][] = $error->getMessageTemplate();
	        } else {
	            $errors[] = $error->getMessageTemplate();
	        }
	    }
	
	    foreach ($form->all() as $child) {
	        if (!$child->isValid()) {
	            $errors[$child->getName()] = $this->getErrorMessages($child);
	        }
	    }
	
	    return $errors;
	}
	
	public function phoneExistsAction( $phone ) {
		$view_data = array('phone' => $phone);
		return $this->render('UKMUserBundle:Registration:phoneExists.html.twig', $view_data );
	}

	public function emailExistsAction( $email ) {
		$view_data = array('email' => $email);
		return $this->render('UKMUserBundle:Registration:emailExists.html.twig', $view_data);
	}
	
	
	public function checkSMSAction(Request $request) {
        $email = $this->get('session')->get('fos_user_send_confirmation_email/email');
        $sent_before = $request->query->get('sent_before');
        $userManager = $this->get('fos_user.user_manager');
		
		if( empty( $email ) ) {
			$this->get('session')->getFlashBag()->set('error', 'Beklager, klarte ikke å finne igjen brukeren');
			return $this->redirectToRoute('ukm_delta_ukmid_homepage');
		}
		
		$user = $userManager->findUserByEmail($email);
		if( is_object( $user ) ) {
			$phone = $user->getPhone();
			
			$view_data = array( 'email' => $email, 'sent_before' => $sent_before, 'phone' => $phone );
			return $this->render('UKMUserBundle:Registration:check-sms.html.twig', $view_data);
		}
		throw new Exception('Beklager, klarte ikke å finne igjen brukeren med mobilnummer '. $email );
	}
	
	public function validateSMSAction(Request $request) {
		$email = $this->get('session')->get('fos_user_send_confirmation_email/email');
		$sms = $request->request->get('smscode');
		
		$userManager = $this->get('fos_user.user_manager');
		$user = $userManager->findUserByEmail($email);

		// User exists ?		
		if( null === $user ) {
			$this->get('session')->getFlashBag()->set('error', 'Beklager, finner ikke brukeren din (feil e-postadresse?)');
			return $this->redirect( $this->get('router')->generate('ukm_user_registration_check_sms') );
		}
		
		if( (int)$user->getSmsValidationCode() !== (int)$sms ) {
			$this->get('session')->getFlashBag()->set('error', 'Feil kode!');
			return $this->redirect( $this->get('router')->generate('ukm_user_registration_check_sms') );
		}
		
		// TODO: Hvis $user->getConfirmationToken() == null, redirect til error-handler..
		// Hvis brukeren hyperklikker på registrer-knappen kan 2 requests fyres av,
		// og brukeren være godkjent allerede før han selv kommer til denne siden
		if( null == $user->getConfirmationToken() ) {
			if( $user->isEnabled() ) {
				die('The Nordboe bug occurred. Please advise');
			}
		}

		// Fordi FOS er teit i nyere versjoner, må vi enten override fos_user_registration_confirm, eller logge inn brukeren direkte.
		return $this->confirmAction($request, $user->getConfirmationToken());
	}


	# Reverse SMS Validation 
	# Skrevet av Asgeir Hustad
	# asgeirsh@ukmmedia.no
	# Høst 2015
	# Funksjonen setter informasjon i SMSValidation-tabellen og
	# rendrer et view som sier at personen skal sende SMS til oss.
	public function noSMSAction($phone) {
		$view_data['translationDomain'] = 'messages';
		$view_data['nummer'] = $phone;

		$userProvider = $this->get('ukm_user.user_provider');
		$user = $userProvider->findUserByPhoneOrEmail($phone);

		// Registrer i SMSValidation-tabellen
		$em = $this->getDoctrine()->getManager();
		$smsval = new SMSValidation();
		// Sett verdier
		$smsval->setUserId($user->getId());
		$smsval->setPhone($phone);
		$smsval->setValidated(false);
		
		$em->persist($smsval);
		$em->flush();

		// Send e-post til support om at Revers-validering er igangsatt for et nummer
		require_once('UKMconfig.inc.php');
		require_once('UKM/mail.class.php');
		$mail = new UKMmail();
		$mail->
			to("support@ukm.no")->
			setFrom('delta@'.UKM_HOSTNAME, 'UKMdelta')->
			subject('Manuell validering for '.$phone)->
			message(
                'Deltaker med mobilnummer '.$phone.' har prøvd å registrere seg, men fikk ikke SMS med godkjenningskode. '.
                'Deltakeren trykte deretter på "Ikke fått SMS"-knappen. '. "\r\n\r\n".
                'Dersom alt er OK vil du få flere e-poster merket med det samme nummeret innen kort tid. '. "\r\n\r\n".
                'For at det skal skje mer med denne saken, må deltakeren sende UKM V '. $user->getId() .' til 1963 fra '. $phone .'. '.
                'Hvis dette ikke skjer, kommer det heller ikke flere e-poster.'. "\r\n".
                'Det er lov å anta at dette problemet løser seg av seg selv, og kan autolukkes i support-systemet.'.
                'Steg 1 av 3.'
            );
		if('ukm.dev' == UKM_HOSTNAME) {
			$this->get('logger')->notice("UKMdelta: Not sending email in dev due to timeouts!");
		} else {
			$this->get('logger')->notice("UKMdelta: Sending reverse sms notification email.");
			$mail_result = $mail->ok();	
		}

		$view_data['kode'] = 'V ' . $user->getId();
		return $this->render('UKMUserBundle:Registration:no-sms.html.twig', $view_data);
	}
	
	# Reverse SMS Validaton
	# Skrevet av Asgeir Hustad
	# asgeirsh@ukmmedia.no
	# Høst 2015
	# Sjekker om SMS er mottatt, 
	# og rendrer et view som viser at vi fortsatt leter,
	# inkl. AJAX-kall hvis ikke.
	public function waitSMSAction($phone) {
		$view_data = array('phone' => $phone);
		$view_data['translationDomain'] = 'messages';
		$view_data['ajax_url'] = $this->generateUrl('ukm_user_registration_check_sms_ajax', array(
			'phone' => $phone));
		if ($this->checkSMSValidation($phone)) {
			// Alt er ok, vi har mottatt SMS og skrudd på brukeren!
			return $this->confirmedAction($this->getRequest());
		}

		return $this->render('UKMUserBundle:Registration:wait-sms.html.twig', $view_data);
	}

	# Reverse SMS Validation
	# Skrevet av Asgeir Hustad
	# asgeirsh@ukmmedia.no
	# Høst 2015
	# Tar inn tlf. nummer og ser etter innkommende SMS
	# Hvis meldingen er mottatt, fiks tabellene og returner true.
	# Hvis meldingen ikke er mottatt, returner false.
	public function checkSMSValidation($phone) {
		$em = $this->getDoctrine()->getManager();
		$r = $this->getDoctrine()->getRepository('UKMNorge\UserBundle\Entity\SMSValidation');
		
		$smsVal = $r->findMostRecentByPhone($phone);
		
		if ($smsVal->getValidated() == true) {	
			$userProvider = $this->get('ukm_user.user_provider');
			$userManager = $this->get('fos_user.user_manager');
			
			$user = $userProvider->findUserByPhoneOrEmail($phone);
			
			// Send e-post til support om at brukeren godkjennes i Deltasystemet.
			require_once('UKMconfig.inc.php');
			require_once('UKM/mail.class.php');
			$mail = new UKMmail();
			$mail->
				to("support@ukm.no")->
				setFrom('delta@'.UKM_HOSTNAME, 'UKMdelta')->
				subject('Re: Manuell validering for '.$phone)->
				message('Deltaker med mobilnummer '.$phone.' har fullført registreringen. Brukeren er opprettet og godkjent med ID: '.$user->getId().'. Steg 3 av 3. freshdesk: lukk denne support-saken.');
			if('ukm.dev' == UKM_HOSTNAME) {
				$this->get('logger')->notice("UKMdelta: Not sending email in dev due to timeouts!");
			} else {
				$this->get('logger')->notice("UKMdelta: Sending reverse sms user approved notification email.");
				$mail_result = $mail->ok();	
			}

			/** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        	$dispatcher = $this->get('event_dispatcher');

        	$user->setConfirmationToken(null);
        	$user->setEnabled(true);			
			
        	$userManager->updateUser($user);

        	// Let the user know his new password.
        	$event = new GetResponseUserEvent($user, $this->get('request'));
        	$dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRM, $event);

        	// Log in user
        	$request = Request::createFromGlobals();
        	$response = new Response();

        	$dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));
			return 1;
		}
		
		return 0;
	}

	public function SMSAjaxAction($phone) {
		
		$resp[] = array('validated' => $this->checkSMSValidation($phone));
		$response = new Response();
		$response->setContent(json_encode($resp));
		
		header('Content-Type: application/json; charset=utf-8');
		
		return $response;
	}

    /**
     * Tell the user his account is now confirmed
     */
    public function confirmedAction(Request $request)
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->get('ukm_user.redirect')->doRedirect();
    }
    
    /**
     * Receive the confirmation token from user email provider, login the user
     */
    public function confirmAction(Request $request, $token)
    {
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByConfirmationToken($token);

        if (null === $user) {
	        return $this->render('UKMUserBundle:Resetting:tokenUsed.html.twig');
        }

        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user->setConfirmationToken(null);
        $user->setEnabled(true);
		
        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRM, $event);

        $userManager->updateUser($user);

        if (null === $response = $event->getResponse()) {
            $url = $this->generateUrl('ukm_delta_ukmid_homepage');
            $response = new RedirectResponse($url);
        }

        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));

        $handler = $this->get('ukm_user.security.authentication.handler.login_success_handler');
        $usertoken = $this->get('security.token_storage')->getToken();   
        $response = $handler->onAuthenticationSuccess($request, $usertoken);

        return $response;

    }
}