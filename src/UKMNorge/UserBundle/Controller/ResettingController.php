<?php

namespace UKMNorge\UserBundle\Controller;
/* FROM PARENT */
	use FOS\UserBundle\FOSUserEvents;
	use FOS\UserBundle\Event\FormEvent;
	use FOS\UserBundle\Event\GetResponseUserEvent;
	use FOS\UserBundle\Event\FilterUserResponseEvent;
	use FOS\UserBundle\Model\UserInterface;
	use Symfony\Bundle\FrameworkBundle\Controller\Controller;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\RedirectResponse;
	use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
/* E.O FROM PARENT */

use UKMNorge\UserBundle\UKMUserEvents;
use FOS\UserBundle\Controller\ResettingController as BaseController;

class ResettingController extends BaseController
{
    /**
     * Request reset user password: submit form and send email
     */
    public function sendEmailAction(Request $request)
    {
        $username = $request->request->get('username');

        /** @var $user UserInterface */
        
        ### CHANGE FROM PARENT (use UKM USER PROVIDER )
        $user = $this->get('ukm_user.user_provider')->findUserByUsernameOrEmail($username);
		### E.O CHANGE
		
        if (null === $user) {
            return $this->render('FOSUserBundle:Resetting:request.html.twig', array(
                'invalid_username' => $username
            ));
        }

        if ($user->isPasswordRequestNonExpired($this->container->getParameter('fos_user.resetting.token_ttl'))) {
            return $this->render('FOSUserBundle:Resetting:passwordAlreadyRequested.html.twig', array('sent' => $user->getPasswordRequestedAt() ));
        }

        if (null === $user->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->get('fos_user.util.token_generator');
            $user->setConfirmationToken($tokenGenerator->generateToken());
        }

        $this->get('fos_user.mailer')->sendResettingEmailMessage($user);
        $user->setPasswordRequestedAt(new \DateTime());
        $this->get('fos_user.user_manager')->updateUser($user);

        return new RedirectResponse($this->generateUrl('fos_user_resetting_check_email',
            array('email' => $user->getPhone())#$this->getObfuscatedEmail($user))
        ));
    }
	    /**
     * Tell the user to check his email provider
     */
    public function checkEmailAction(Request $request)
    {
        $phone = $request->query->get('email');

        if (empty($phone)) {
            // the user does not come from the sendEmail action
            return new RedirectResponse($this->generateUrl('fos_user_resetting_request'));
        }

        return $this->render('UKMUserBundle:Resetting:checkEmail.html.twig', array(
            'phone' => $phone,
        ));
	}
}
