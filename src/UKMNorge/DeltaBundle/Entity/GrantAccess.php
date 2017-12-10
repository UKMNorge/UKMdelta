<?php

namespace UKMNorge\DeltaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GrantAccess
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class GrantAccess
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
     * @ORM\Column(name="UKMid", type="integer")
     */
    private $ukmid;

    /**
     * @var integer
     *
     * @ORM\Column(name="RequestBand", type="integer")
     */
    private $requestBand;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="RequestTime", type="datetime")
     */
    private $requestTime;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="AlertSent", type="datetime")
     */
    private $alertSent;

    /**
     * @var boolean
     *
     * @ORM\Column(name="Approved", type="boolean")
     */
    private $approved;


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
     * Set ukmid
     *
     * @param integer $ukmid
     * @return GrantAccess
     */
    public function setUKMid($uKMid)
    {
        $this->ukmid = $ukmid;

        return $this;
    }

    /**
     * Get uKMid
     *
     * @return integer 
     */
    public function getUKMid()
    {
        return $this->ukmid;
    }

    /**
     * Set requestBand
     *
     * @param integer $requestBand
     * @return GrantAccess
     */
    public function setRequestBand($requestBand)
    {
        $this->requestBand = $requestBand;

        return $this;
    }

    /**
     * Get requestBand
     *
     * @return integer 
     */
    public function getRequestBand()
    {
        return $this->requestBand;
    }

    /**
     * Set requestTime
     *
     * @param \DateTime $requestTime
     * @return GrantAccess
     */
    public function setRequestTime($requestTime)
    {
        $this->requestTime = $requestTime;

        return $this;
    }

    /**
     * Get requestTime
     *
     * @return \DateTime 
     */
    public function getRequestTime()
    {
        return $this->requestTime;
    }

    /**
     * Set alertSent
     *
     * @param \DateTime $alertSent
     * @return GrantAccess
     */
    public function setAlertSent($alertSent)
    {
        $this->alertSent = $alertSent;

        return $this;
    }

    /**
     * Get alertSent
     *
     * @return \DateTime 
     */
    public function getAlertSent()
    {
        return $this->alertSent;
    }

    /**
     * Set approved
     *
     * @param boolean $approved
     * @return GrantAccess
     */
    public function setApproved($approved)
    {
        $this->approved = $approved;

        return $this;
    }

    /**
     * Get approved
     *
     * @return boolean 
     */
    public function getApproved()
    {
        return $this->approved;
    }
}
