<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PrivateKeyMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'mpencrypt_private_keys', PrivateKey::class);
    }

    public function findByUser(string $uid): ?PrivateKey {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->setMaxResults(1);
        $entities = $this->findEntities($qb);
        return $entities[0] ?? null;
    }

    public function deleteByUser(string $uid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->executeStatement();
    }
}

