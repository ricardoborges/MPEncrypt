<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Controller;

use OCA\MPEncrypt\Db\Recipient;
use OCA\MPEncrypt\Db\RecipientMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class RecipientController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private RecipientMapper $mapper,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    private function getUid(): string {
        $user = $this->userSession->getUser();
        return $user?->getUID() ?? '';
    }

    /**
     * @return DataResponse<Http::STATUS_OK, array{items: array<int, array>}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/recipients')]
    public function list(): DataResponse {
        $uid = $this->getUid();
        $items = array_map(static fn(Recipient $r) => $r->jsonSerialize(), $this->mapper->findAllByUser($uid));
        return new DataResponse(['items' => $items]);
    }

    /**
     * @return DataResponse<Http::STATUS_CREATED, array{id: int}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/recipients')]
    public function create(string $name = '', string $publicKey = ''): DataResponse {
        $name = trim($name);
        $publicKey = trim($publicKey);
        if ($name === '' || $publicKey === '') {
            return new DataResponse(['message' => 'Invalid payload'], Http::STATUS_BAD_REQUEST);
        }

        $uid = $this->getUid();
        $now = time();
        $entity = new Recipient();
        $entity->setUid($uid);
        $entity->setName($name);
        $entity->setPublicKey($publicKey);
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        /** @var Recipient $saved */
        $saved = $this->mapper->insert($entity);
        return new DataResponse(['id' => (int)$saved->getId()], Http::STATUS_CREATED);
    }

    /**
     * @return DataResponse<Http::STATUS_NO_CONTENT, array<never, never>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'DELETE', url: '/recipients/{id}')]
    public function delete(int $id): DataResponse {
        $uid = $this->getUid();
        $entity = $this->mapper->findByIdForUser($id, $uid);
        if ($entity === null) {
            return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
        $this->mapper->delete($entity);
        return new DataResponse([], Http::STATUS_NO_CONTENT);
    }
}

