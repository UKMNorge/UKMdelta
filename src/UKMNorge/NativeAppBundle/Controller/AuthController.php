<?php

namespace UKMNorge\NativeAppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class AuthController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('UKMNAppBundle:Default:index.html.twig', array('name' => $name));
    }
}
