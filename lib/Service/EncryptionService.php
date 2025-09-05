<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Service;

use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Node\File as NCFile;
use OCP\Files\Node\Folder as NCFolder;
use OCP\IUserSession;
use RuntimeException;

class EncryptionService {
    public function __construct(
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
    ) {}

    /**
     * Encrypt a file for the current user using an ASCII-armored PGP public key.
     * Returns the new file node path relative to the user folder.
     *
     * @throws RuntimeException on failures
     */
    public function encryptFileForRecipient(string $publicKey, ?int $fileId = null, ?string $filePath = null): string {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        if ($uid === '') {
            throw new RuntimeException('Usuário não autenticado');
        }

        // Resolve source file
        $file = $this->resolveFile($uid, $fileId, $filePath);
        if (!$file instanceof NCFile) {
            throw new RuntimeException('Arquivo inválido');
        }

        $plain = $file->getContent();
        if ($plain === false) {
            throw new RuntimeException('Não foi possível ler o arquivo');
        }

        $cipher = $this->encryptWithGnuPG($publicKey, $plain);
        if ($cipher === '') {
            throw new RuntimeException('Falha ao criptografar o arquivo');
        }

        // Determine destination name and folder
        $parent = $file->getParent();
        if (!$parent instanceof NCFolder) {
            throw new RuntimeException('Pasta de destino inválida');
        }

        $newName = $this->buildEncryptedFileName($file->getName(), false);
        $dest = $this->createUniqueFile($parent, $newName, $cipher);

        // Return a relative path for convenience
        return $this->relativePathForUser($uid, $dest);
    }

    private function resolveFile(string $uid, ?int $fileId, ?string $filePath): ?NCFile {
        // Try by file ID first
        if ($fileId !== null && $fileId > 0) {
            $nodes = $this->rootFolder->getById($fileId);
            foreach ($nodes as $node) {
                if ($node instanceof NCFile && $this->belongsToUser($uid, $node)) {
                    return $node;
                }
            }
        }
        // Fallback to path under user's folder
        if ($filePath !== null && $filePath !== '') {
            // Normalize to a path relative to user's folder
            $filePath = ltrim($filePath, '/');
            $userFolder = $this->rootFolder->getUserFolder($uid);
            try {
                $node = $userFolder->get($filePath);
                if ($node instanceof NCFile) {
                    return $node;
                }
            } catch (\Throwable) {
                // ignore
            }
        }
        return null;
    }

    private function belongsToUser(string $uid, Node $node): bool {
        try {
            $path = $node->getPath();
            return str_starts_with($path, '/'.$uid.'/files');
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildEncryptedFileName(string $original, bool $asciiArmor): string {
        $suffix = $asciiArmor ? '.asc' : '.pgp';
        return $original . $suffix;
    }

    private function createUniqueFile(NCFolder $folder, string $name, string $contents): NCFile {
        $candidate = $name;
        $counter = 0;
        while ($folder->nodeExists($candidate)) {
            $counter++;
            $candidate = $this->appendBeforeSuffix($name, " ($counter)");
        }
        /** @var NCFile $file */
        $file = $folder->newFile($candidate, $contents);
        return $file;
    }

    private function appendBeforeSuffix(string $name, string $insert): string {
        $pos = strrpos($name, '.');
        if ($pos === false) {
            return $name . $insert;
        }
        return substr($name, 0, $pos) . $insert . substr($name, $pos);
    }

    private function relativePathForUser(string $uid, NCFile $file): string {
        $full = $file->getPath();
        $prefix = '/'.$uid.'/files/';
        if (str_starts_with($full, $prefix)) {
            return substr($full, strlen($prefix));
        }
        return $full;
    }

    private function encryptWithGnuPG(string $publicKey, string $data): string {
        // Prefer OO API if available
        if (class_exists('gnupg')) {
            /** @psalm-suppress UndefinedClass */
            $gpg = new \gnupg();
            // Armor off => binary .pgp file
            if (method_exists($gpg, 'setarmor')) {
                $gpg->setarmor(false);
            }
            $errorMode = \defined('GNUPG_ERROR_EXCEPTION') ? \GNUPG_ERROR_EXCEPTION : 2;
            if (method_exists($gpg, 'seterrormode')) {
                $gpg->seterrormode($errorMode);
            }
            $import = $gpg->import($publicKey);
            $fingerprint = is_array($import) ? ($import['fingerprint'] ?? null) : null;
            if (!$fingerprint) {
                throw new RuntimeException('Chave pública inválida');
            }
            $gpg->addencryptkey($fingerprint);
            $out = $gpg->encrypt($data);
            if (!is_string($out) || $out === '') {
                throw new RuntimeException('Falha na criptografia GnuPG');
            }
            return $out;
        }

        // Fallback to procedural API
        if (function_exists('gnupg_init')) {
            $res = \gnupg_init();
            \gnupg_setarmor($res, false);
            $import = \gnupg_import($res, $publicKey);
            $fingerprint = is_array($import) ? ($import['fingerprint'] ?? null) : null;
            if (!$fingerprint) {
                throw new RuntimeException('Chave pública inválida');
            }
            \gnupg_addencryptkey($res, $fingerprint);
            $out = \gnupg_encrypt($res, $data);
            if (!is_string($out) || $out === '') {
                throw new RuntimeException('Falha na criptografia GnuPG');
            }
            return $out;
        }

        throw new RuntimeException('Extensão PHP GnuPG não disponível no servidor');
    }
}
