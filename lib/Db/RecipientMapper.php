<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class RecipientMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'mpencrypt_recipients', Recipient::class);
    }

    /**
     * @return Recipient[]
     */
    public function findAllByUser(string $uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
        return $this->findEntities($qb);
    }

    public function findByIdForUser(int $id, string $uid): ?Recipient {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->setMaxResults(1);
        $entities = $this->findEntities($qb);
        return $entities[0] ?? null;
    }
}

