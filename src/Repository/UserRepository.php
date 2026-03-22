<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, PaginationInterface
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedUsers(array $filters, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('u.email LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['role']) && $filters['role'] !== 'ALL') {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $filters['role'] . '"%');
        }

        if (!empty($filters['companyId'])) {
            $qb->andWhere('u.company = :companyId')
                ->setParameter('companyId', $filters['companyId']);
        }

        if (!empty($filters['excludeSuperAdmin'])) {
            $qb->andWhere('u.roles NOT LIKE :superAdminRole')
                ->setParameter('superAdminRole', '%"ROLE_SUPER_ADMIN"%');
        }

        return $this->paginate($qb, $page, $limit);
    }
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}