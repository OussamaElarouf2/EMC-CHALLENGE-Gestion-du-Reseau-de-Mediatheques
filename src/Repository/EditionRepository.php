<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Edition;
use App\Enum\MediaType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Edition>
 */
class EditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Edition::class);
    }

    /**
     * Search catalogue with optional text query and media type filter.
     *
     * @return array{items: list<Edition>, total: int, page: int, pages: int}
     */
    public function searchCatalogue(?string $query = null, ?MediaType $mediaType = null, int $page = 1, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.work', 'w')
            ->leftJoin('e.contributors', 'ec')
            ->leftJoin('ec.contributor', 'c')
            ->addSelect('w', 'ec', 'c');

        if ($query !== null && trim($query) !== '') {
            $cleanQuery = trim($query);
            $isbnCandidate = str_replace(['-', ' '], '', $cleanQuery);

            $qb->andWhere('e.title LIKE :q OR w.originalTitle LIKE :q OR c.name LIKE :q OR e.isbn LIKE :isbn')
                ->setParameter('q', '%' . $cleanQuery . '%')
                ->setParameter('isbn', '%' . $isbnCandidate . '%');
        }

        if ($mediaType !== null) {
            $qb->andWhere('e.mediaType = :mediaType')
                ->setParameter('mediaType', $mediaType);
        }

        $qb->orderBy('e.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);
        $total = count($paginator);
        $pages = (int) max(1, ceil($total / $limit));

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
