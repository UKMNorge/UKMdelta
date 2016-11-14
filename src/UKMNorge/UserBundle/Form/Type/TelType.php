<?php

namespace UKMNorge\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TelType extends AbstractType
{
    public function getParent()
    {
        return 'text';
    }

    public function getName()
    {
    	return 'tel';
    }
}