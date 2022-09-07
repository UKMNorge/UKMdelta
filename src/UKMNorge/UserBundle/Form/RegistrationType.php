<?php
// src/UKMUserBundle/Form/RegistrationType.php

namespace UKMNorge\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use FOS\UserBundle\Form\Type\RegistrationFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use UKMNorge\APIBundle\Services\SessionService;
use Symfony\Component\HttpFoundation\Session\Session;





// use UKMNorge\UserBundle\Form\Type\TelType;

class RegistrationType extends AbstractType
{
    public function __construct($container) {
        
        $this->container = $container;
        $this->session = $this->getSession();
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    parent::buildForm($builder, $options);

        $builder->remove('username')
        		->remove('plainPassword');



        $builder->add('facebook_id', HiddenType::class, array(
                    'data' => $this->session->get('facebook_id')));
                    
        $builder->add('first_name', TextType::class, array(
                    'label' => 'ukm_user.first_name', 
                    'data' => $this->session->get('first_name')));
                    
        $builder->add('last_name', TextType::class, array(
                    'label' => 'ukm_user.last_name',
                    'data' => $this->session->get('last_name')))
        		->add('phone', TelType::class, array('label' => 'ukm_user.phone'));
    }

    public function getParent()
    {
        return "FOS\UserBundle\Form\Type\RegistrationFormType";
    }

    public function setDefaultOptions(OptionsResolver $r) {
        /** @var OptionResolver $resolver */
        $this->configureOptions($r);
    }

    public function getName()
    {
        return 'UKM_user_registration';
    }

    private function getSession() : Session {
        $session = SessionService::getSession();
        return $session;
    }
    
}