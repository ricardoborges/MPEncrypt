<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20250905 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('mpencrypt_private_keys')) {
            return null;
        }

        $table = $schema->createTable('mpencrypt_private_keys');
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->addColumn('uid', 'string', [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addColumn('private_key', 'text', [
            'notnull' => true,
        ]);
        $table->addColumn('created_at', 'integer', [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('updated_at', 'integer', [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uid'], 'mpencrypt_priv_uid_unq');

        return $schema;
    }
}

