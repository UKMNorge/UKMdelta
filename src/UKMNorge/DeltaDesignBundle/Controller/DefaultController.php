<?php

namespace UKMNorge\DeltaDesignBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('UKMDeltaDesignBundle:Default:index.html.twig', array('name' => $name));
    }
}
