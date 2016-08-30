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
}
