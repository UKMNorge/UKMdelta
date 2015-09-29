<?php

namespace UKMNorge\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class UKMUserBundle extends Bundle
{
    public function getParent()
    {
        return 'FOSUserBundle';
    }
}
