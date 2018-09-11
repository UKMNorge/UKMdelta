<?php
namespace UKMNorge\UserBundle\EventListener;

use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Event\FilterUserResponseEvent;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use UKMNorge\UserBundle\UKMUserEvents;
use Exception;


/**
 * Listener responsible to change the redirection at the end of the password resetting
 */
class ChangePasswordListener implements EventSubscriberInterface
{
    private $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FOSUserEvents::CHANGE_PASSWORD_SUCCESS => 'onChangePasswordSuccess',
        );
    }

    public function onChangePasswordSuccess(FormEvent $event)
    {
        $url = $this->router->generate('ukm_delta_ukmid_homepage');

        $event->setResponse(new RedirectResponse($url));
    }
}