<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use UKMNorge\UserBundle\Services\UserService;
use Exception;

class DefaultController extends Controller
{
    /**
     * @Route("/app/example", name="homepage")
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/exceptionTest/", name="Exception-test")
     */
    public function exceptionTestAction() {
    	throw new Exception("Dette er en test-feil!");
    }

    /**
     * @Route("/ukmid/loggedOutTest/", name="LoggedOut-test")
     */
    public function loggedOutTestAction() {
        $usertoken = new UsernamePasswordToken("anon", "anon", "ukm_delta_wall", array("ROLE_USER"));
        $this->container->get('security.token_storage')->setToken($usertoken);
        
        $userObj = new UserService($this->container);
        
        $user = $userObj->getCurrentUser();
        $user->getPameldUser();
        return $this->redirectToRoute('ukm_delta_ukmid_homepage');
    }
}
