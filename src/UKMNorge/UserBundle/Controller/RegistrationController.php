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

use UKMNorge\UserBundle\UKMUserEvents;
use FOS\UserBundle\Controller\RegistrationController as BaseController;

class RegistrationController extends BaseController
{
	
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
        $form->handleRequest($request);
        
		// CASE 1: Form submitted, valid and user creation is possible
        if ($form->isValid()) {
            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $userManager->updateUser($user);

			$response = $event->getResponse();
            if (null === $response) {
                $url = $this->generateUrl('fos_user_registration_confirmed');
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
					    return $this->redirectToRoute('ukm_user_registration_existing_phone', array('phone' => $phone));
				    }
			    }
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
	
	
	public function checkSMSAction(Request $request) {
        $email = $this->get('session')->get('fos_user_send_confirmation_email/email');
        $sent_before = $request->query->get('sent_before');

        $view_data = array( 'email' => $email, 'sent_before' => $sent_before );
        return $this->render('UKMUserBundle:Registration:check-sms.html.twig', $view_data);
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
		
		$url = $this->get('router')->generate('fos_user_registration_confirm', array('token' => $user->getConfirmationToken()), true);
		return $this->redirect( $url );
	}
	
    /**
     * Tell the user his account is now confirmed
     */
    public function confirmedAction()
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->redirect( $this->get('router')->generate('ukm_delta_ukmid_homepage') );
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
//            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user->setConfirmationToken(null);
        $user->setEnabled(true);
		
        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRM, $event);

        $userManager->updateUser($user);

        if (null === $response = $event->getResponse()) {
            $url = $this->generateUrl('fos_user_registration_confirmed');
            $response = new RedirectResponse($url);
        }

        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));

		return $this->confirmedAction();
    }
}