<?php

namespace UKMNorge\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * DipToken
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="UKMNorge\UserBundle\Entity\DipTokenRepository")
 */
class DipToken
{
    public function __construct() {        
        $this->timeCreated = new DateTime('now');
    }
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string", length=255)
     */
    private $token;

    /**
     * @var string
     *
     * @ORM\Column(name="location", type="string", length=255)
     */
    private $location;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="time_created", type="datetime")
     */
    private $timeCreated;

    /**
     *
     *
     * @ORM\Column(name="user_id", type="integer", nullable=true)
     */
    private $userId = null;

    /**
     *
     * @ORM\Column(name="time_used", type="datetime", nullable=true)
     */
    private $timeUsed;

    /**
     * @var boolean
     *
     * @ORM\Column(name="active", type="boolean")
     */
    private $active=false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expires", type="datetime", nullable=true)
     */
    private $expires;

    /**
     * @var string
     *
     * @ORM\Column(name="uuid", type="string", length=36, nullable=true)
     */
    private $UUID;

    /**
     * @var string
     *
     * @ORM\Column(name="uuid_nicename", type="string", length=60, nullable=true)
     */
    private $UUID_nicename;

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
     * Set token
     *
     * @param string $token
     * @return DipToken
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
     * Set location
     *
     * @param string $location
     * @return DipToken
     */
    public function setLocation($location)
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Get location
     *
     * @return string 
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set timeCreated
     *
     * @param \DateTime $timeCreated
     * @return DipToken
     */
    public function setTimeCreated($timeCreated)
    {
        $this->timeCreated = $timeCreated;

        return $this;
    }

    /**
     * Get timeCreated
     *
     * @return \DateTime 
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     * @return DipToken
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Get userId
     *
     * @return integer 
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set timeUsed
     *
     * @param \DateTime $timeUsed
     * @return DipToken
     */
    public function setTimeUsed($timeUsed)
    {
        $this->timeUsed = $timeUsed;

        return $this;
    }

    /**
     * Get timeUsed
     *
     * @return \DateTime 
     */
    public function getTimeUsed()
    {
        return $this->timeUsed;
    }

    /**
     * Set active
     *
     * @param boolean $active
     * @return DipToken
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean 
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set expires
     *
     * @param \DateTime $expires
     * @return DipToken
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * Get expires
     *
     * @return \DateTime 
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * Set UUID
     *
     * @param string $uUID
     * @return DipToken
     */
    public function setUUID($uUID)
    {
        $this->UUID = $uUID;

        return $this;
    }

    /**
     * Get UUID
     *
     * @return string 
     */
    public function getUUID()
    {
        return $this->UUID;
    }

    /**
     * Set UUID_nicename
     *
     * @param string $uUIDNicename
     * @return DipToken
     */
    public function setUUIDNicename($uUIDNicename)
    {
        $this->UUID_nicename = $uUIDNicename;

        return $this;
    }

    /**
     * Get UUID_nicename
     *
     * @return string 
     */
    public function getUUIDNicename()
    {
        return $this->UUID_nicename;
    }
}
