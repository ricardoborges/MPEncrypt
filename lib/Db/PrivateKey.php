<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class PrivateKey extends Entity implements JsonSerializable {
    public $id = null;
    protected $uid;
    protected $privateKey;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('id', 'int');
        $this->addType('createdAt', 'int');
        $this->addType('updatedAt', 'int');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

