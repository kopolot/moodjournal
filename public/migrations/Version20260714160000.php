<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mood entries + user gamification fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD xp_total INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD current_streak INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD longest_streak INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_mood_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD subscription_tier VARCHAR(32) DEFAULT \'free\' NOT NULL');
        $this->addSql('COMMENT ON COLUMN "user".last_mood_date IS \'(DC2Type:date_immutable)\'');

        $this->addSql('CREATE TABLE mood_entry (id UUID NOT NULL, user_id UUID NOT NULL, overall_mood SMALLINT NOT NULL, aspects JSON NOT NULL, note TEXT DEFAULT NULL, xp_earned INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_mood_entry_user_created ON mood_entry (user_id, created_at)');
        $this->addSql('COMMENT ON COLUMN mood_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN mood_entry.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN mood_entry.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN mood_entry.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE mood_entry ADD CONSTRAINT FK_MOOD_ENTRY_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mood_entry DROP CONSTRAINT FK_MOOD_ENTRY_USER');
        $this->addSql('DROP TABLE mood_entry');
        $this->addSql('ALTER TABLE "user" DROP xp_total');
        $this->addSql('ALTER TABLE "user" DROP current_streak');
        $this->addSql('ALTER TABLE "user" DROP longest_streak');
        $this->addSql('ALTER TABLE "user" DROP last_mood_date');
        $this->addSql('ALTER TABLE "user" DROP subscription_tier');
    }
}
