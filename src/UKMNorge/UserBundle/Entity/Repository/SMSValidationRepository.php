<?php

namespace UKMNorge\UserBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * SMSValidationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SMSValidationRepository extends EntityRepository
{
	public function findMostRecentByPhone($phone) {
		$query = $this->createQueryBuilder('s')
			->select('s')
			->where('s.phone = :phone')
			->setParameter('phone', $phone)
			->orderBy('s.created', 'DESC')
			->setMaxResults(1)
			->getQuery();
		$result = $query->getSingleResult();
		return $result;
	}
}
