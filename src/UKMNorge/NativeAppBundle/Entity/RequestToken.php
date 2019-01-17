<?php

namespace UKMNorge\NativeAppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RequestToken
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="UKMNorge\NativeAppBundle\Entity\RequestTokenRepository")
 */
class RequestToken
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="User", type="integer")
     */
    private $user;

    /**
     * @var boolean
     *
     * @ORM\Column(name="Used", type="boolean")
     */
    private $used;

    /**
     * @var string
     *
     * @ORM\Column(name="AppUUID", type="string", length=36)
     */
    private $appUUID;

    /**
     * @var string
     *
     * @ORM\Column(name="Token", type="string", length=100)
     */
    private $token;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="Time", type="datetime")
     */
    private $time;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set user
     *
     * @param integer $user
     * @return RequestToken
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return integer 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set used
     *
     * @param boolean $used
     * @return RequestToken
     */
    public function setUsed($used)
    {
        $this->used = $used;

        return $this;
    }

    /**
     * Get used
     *
     * @return boolean 
     */
    public function getUsed()
    {
        return $this->used;
    }

    /**
     * Set appUUID
     *
     * @param string $appUUID
     * @return RequestToken
     */
    public function setAppUUID($appUUID)
    {
        $this->appUUID = $appUUID;

        return $this;
    }

    /**
     * Get appUUID
     *
     * @return string 
     */
    public function getAppUUID()
    {
        return $this->appUUID;
    }

    /**
     * Set token
     *
     * @param string $token
     * @return RequestToken
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get token
     *
     * @return string 
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set time
     *
     * @param \DateTime $time
     * @return RequestToken
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Get time
     *
     * @return \DateTime 
     */
    public function getTime()
    {
        return $this->time;
    }
}
