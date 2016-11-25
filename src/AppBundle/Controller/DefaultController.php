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
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/exceptionTest/", name="Exception-test")
     */
    public function exceptionTestAction() {
    	throw new Exception("Dette er en test-feil!");
    }
}
