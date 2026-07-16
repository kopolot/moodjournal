<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stripe customer / subscription ids on user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD stripe_customer_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD stripe_subscription_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_STRIPE_CUSTOMER ON "user" (stripe_customer_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_STRIPE_SUBSCRIPTION ON "user" (stripe_subscription_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_STRIPE_CUSTOMER');
        $this->addSql('DROP INDEX UNIQ_USER_STRIPE_SUBSCRIPTION');
        $this->addSql('ALTER TABLE "user" DROP stripe_customer_id');
        $this->addSql('ALTER TABLE "user" DROP stripe_subscription_id');
    }
}
