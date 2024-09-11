<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Account\Business\Command\RegisterFirstAccount;
use App\Account\Business\UseCase\RegisterFirstAccountUseCase;
use App\Utils\Service\MigrationWithContainer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230222104500 extends AbstractMigration
{
    use MigrationWithContainer;
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $registerFirstAccountUseCase = $this->container->get(RegisterFirstAccountUseCase::class);

        $command = new RegisterFirstAccount('asd@asd.ru', 'asd');
        $registerFirstAccountUseCase->handle($command);

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS jwt_user');
        $this->addSql('TRUNCATE account_event_store');

    }
}
