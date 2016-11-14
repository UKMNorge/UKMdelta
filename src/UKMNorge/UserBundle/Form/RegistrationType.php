<?php
// src/UKMUserBundle/Form/RegistrationType.php

namespace UKMNorge\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use UKMNorge\UserBundle\Form\Type\TelType;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    parent::buildForm($builder, $options);

        $builder->remove('username')
        		->remove('plainPassword');

        $builder->add('first_name', 'text', array('label' => 'ukm_user.first_name'))
        		->add('last_name', 'text', array('label' => 'ukm_user.last_name'))
        		->add('phone', new TelType(), array('label' => 'ukm_user.phone'));
    }

    public function getParent()
    {
        return 'fos_user_registration';
    }

    public function getName()
    {
        return 'UKM_user_registration';
    }
}