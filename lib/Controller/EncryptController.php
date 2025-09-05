<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Controller;

use OCA\MPEncrypt\Db\RecipientMapper;
use OCA\MPEncrypt\Service\EncryptionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class EncryptController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private RecipientMapper $recipients,
        private EncryptionService $encryptor,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @return DataResponse<Http::STATUS_OK, array{path: string, name: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/encrypt')]
    public function encrypt(int $recipientId = 0, int $fileId = 0, string $filePath = ''): DataResponse {
        if ($recipientId <= 0) {
            return new DataResponse(['message' => 'Recipient inválido'], Http::STATUS_BAD_REQUEST);
        }
        $recipient = $this->recipients->findByIdForUser($recipientId, $this->getUid());
        if ($recipient === null) {
            return new DataResponse(['message' => 'Recipient não encontrado'], Http::STATUS_NOT_FOUND);
        }

        try {
            $newRelPath = $this->encryptor->encryptFileForRecipient($recipient->getPublicKey(), $fileId > 0 ? $fileId : null, $filePath !== '' ? $filePath : null);
        } catch (\Throwable $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $name = basename($newRelPath);
        return new DataResponse(['path' => $newRelPath, 'name' => $name]);
    }

    private function getUid(): string {
        /** @var \OCP\IUserSession $userSession */
        $userSession = \OC::$server->get(\OCP\IUserSession::class);
        $user = $userSession->getUser();
        return $user?->getUID() ?? '';
    }
}

