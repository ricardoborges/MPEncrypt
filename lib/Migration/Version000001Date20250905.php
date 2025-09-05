<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20250905 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('mpencrypt_recipients')) {
            return null;
        }

        $table = $schema->createTable('mpencrypt_recipients');
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->addColumn('uid', 'string', [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addColumn('name', 'string', [
            'length' => 190,
            'notnull' => true,
        ]);
        $table->addColumn('public_key', 'text', [
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
        $table->addIndex(['uid'], 'mpencrypt_rec_uid_idx');

        return $schema;
    }
}

