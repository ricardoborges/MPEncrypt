<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Recipient extends Entity implements JsonSerializable {
    // Must be public and untyped to match OCP\AppFramework\Db\Entity expectations
    public $id = null;
    protected $uid;
    protected $name;
    protected $publicKey;
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
            'name' => $this->name,
            'publicKey' => $this->publicKey,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
