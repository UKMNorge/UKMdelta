<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Exception;

class DefaultController extends Controller
{
    /**
     * @Route("/app/example", name="homepage")
     */
    public function indexAction()
    {
        $this->get('logger')->error("Example logging stuff!");
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/exceptionTest/", name="Exception-test")
     */
    public function exceptionTestAction() {
        $this->get('logger')->error("Example logging stuff!");
    	throw new Exception("Dette er en test-feil!");
    }
}
