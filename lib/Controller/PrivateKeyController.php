<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Controller;

use OCA\MPEncrypt\Db\PrivateKey;
use OCA\MPEncrypt\Db\PrivateKeyMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class PrivateKeyController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private PrivateKeyMapper $mapper,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    private function getUid(): string {
        $user = $this->userSession->getUser();
        return $user?->getUID() ?? '';
    }

    /**
     * Return metadata only, not the key material.
     * @return DataResponse<Http::STATUS_OK, array{exists: bool, createdAt?: int, updatedAt?: int}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/private-key')]
    public function show(): DataResponse {
        $uid = $this->getUid();
        $entity = $this->mapper->findByUser($uid);
        if ($entity === null) {
            return new DataResponse(['exists' => false]);
        }
        return new DataResponse([
            'exists' => true,
            'createdAt' => (int)$entity->getCreatedAt(),
            'updatedAt' => (int)$entity->getUpdatedAt(),
        ]);
    }

    /**
     * Return the private key value to the logged-in user.
     * @return DataResponse<Http::STATUS_OK, array{privateKey: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/private-key/value')]
    public function value(): DataResponse {
        $uid = $this->getUid();
        $entity = $this->mapper->findByUser($uid);
        if ($entity === null) {
            return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse(['privateKey' => (string)$entity->getPrivateKey()]);
    }

    /**
     * Create or replace the private key for the user.
     * @return DataResponse<Http::STATUS_CREATED, array{ok: bool}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/private-key')]
    public function store(string $privateKey = ''): DataResponse {
        $privateKey = trim($privateKey);
        if ($privateKey === '') {
            return new DataResponse(['message' => 'Invalid payload'], Http::STATUS_BAD_REQUEST);
        }
        $uid = $this->getUid();
        $now = time();
        $existing = $this->mapper->findByUser($uid);
        if ($existing instanceof PrivateKey) {
            $existing->setPrivateKey($privateKey);
            $existing->setUpdatedAt($now);
            $this->mapper->update($existing);
        } else {
            $entity = new PrivateKey();
            $entity->setUid($uid);
            $entity->setPrivateKey($privateKey);
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
            $this->mapper->insert($entity);
        }
        return new DataResponse(['ok' => true], Http::STATUS_CREATED);
    }

    /**
     * @return DataResponse<Http::STATUS_NO_CONTENT, array<never, never>, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'DELETE', url: '/private-key')]
    public function destroy(): DataResponse {
        $uid = $this->getUid();
        $this->mapper->deleteByUser($uid);
        return new DataResponse([], Http::STATUS_NO_CONTENT);
    }
}

