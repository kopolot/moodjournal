<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Subscription expiry timestamp';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD subscription_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".subscription_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP subscription_expires_at');
    }
}
