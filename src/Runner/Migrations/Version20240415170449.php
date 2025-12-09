<?php

declare(strict_types=1);

namespace App\Runner\Migrations;

use App\Runner\Business\UseCase\SozdatDefoltniiRunnerUseCase;
use App\Utils\Service\MigrationWithContainer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240415170449 extends AbstractMigration
{
    use MigrationWithContainer;
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sut = $this->container->get(SozdatDefoltniiRunnerUseCase::class);
        /** @var SozdatDefoltniiRunnerUseCase $sut*/
        $sut->handle();

    }

    public function down(Schema $schema): void
    {
        $this->addSql('TRUNCATE runner_state');
    }
}
