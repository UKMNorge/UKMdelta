<?php

namespace UKMNorge\DeltaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * HideCampaign
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="UKMNorge\DeltaBundle\Entity\HideCampaignRepository")
 */
class HideCampaign
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
     * @var string
     *
     * @ORM\Column(name="Campaign", type="string", length=80)
     */
    private $campaign;

    /**
     * @var integer
     *
     * @ORM\Column(name="UserId", type="integer")
     */
    private $userId;


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
     * Set campaign
     *
     * @param string $campaign
     * @return HideCampaign
     */
    public function setCampaign($campaign)
    {
        $this->campaign = $campaign;

        return $this;
    }

    /**
     * Get campaign
     *
     * @return string 
     */
    public function getCampaign()
    {
        return $this->campaign;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     * @return HideCampaign
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
}
