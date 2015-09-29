<?php
// src/UKMUserBundle/Form/RegistrationType.php

namespace UKMNorge\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    parent::buildForm($builder, $options);

        $builder->remove('username')
        		->remove('plainPassword');

        $builder->add('first_name', 'text', array('label' => 'Fornavn'))
        		->add('last_name', 'text', array('label' => 'Etternavn'))
        		->add('phone', 'number', array('label' => 'Mobil'));
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