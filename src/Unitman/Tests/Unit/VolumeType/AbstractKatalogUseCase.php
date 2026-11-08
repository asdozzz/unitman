<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\VolumeType\VolumeType;
use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;
use App\Unitman\Business\UseCase\VolumeType\ProverkaDostupaKKatalogu;
use App\Unitman\Tests\Support\FakeMediatekaService;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\InMemoryKatalogStorage;
use App\Unitman\Tests\Support\InMemoryProjectRepository;
use App\Unitman\Tests\Support\InMemoryVolumeTypeFileRepository;
use App\Unitman\Tests\Support\InMemoryVolumeTypeRepository;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Общая подготовка тестов каталога типов томов: хранилище в памяти,
 * два проекта (администратором каждого является свой пользователь).
 */
abstract class AbstractKatalogUseCase extends TestCase
{
    protected const PROJECT_ID = 'project-1';
    protected const OTHER_PROJECT_ID = 'project-2';
    protected const ADMIN_ID = 'admin-1';
    protected const OTHER_ADMIN_ID = 'admin-2';
    protected const USER_ID = 'user-1';
    protected const STRANGER_ID = 'stranger-1';

    protected InMemoryKatalogStorage $storage;
    protected InMemoryVolumeTypeRepository $typeRepository;
    protected InMemoryVolumeTypeFileRepository $fileRepository;
    protected FakeMediatekaService $mediatekaService;
    protected FakeUnitmanSecurityService $securityService;
    protected InMemoryProjectRepository $projectRepository;
    protected SequenceGuidGenerator $guidGenerator;
    protected ProverkaDostupaKKatalogu $proverkaDostupa;

    protected function setUp(): void
    {
        $this->storage = new InMemoryKatalogStorage();
        $this->typeRepository = new InMemoryVolumeTypeRepository($this->storage);
        $this->fileRepository = new InMemoryVolumeTypeFileRepository($this->storage);
        $this->mediatekaService = new FakeMediatekaService();
        $this->securityService = new FakeUnitmanSecurityService(self::ADMIN_ID);
        $this->guidGenerator = new SequenceGuidGenerator();

        $this->projectRepository = new InMemoryProjectRepository();
        $this->projectRepository->save($this->sozdatProekt(self::PROJECT_ID, self::ADMIN_ID));
        $this->projectRepository->save($this->sozdatProekt(self::OTHER_PROJECT_ID, self::OTHER_ADMIN_ID));

        $this->proverkaDostupa = new ProverkaDostupaKKatalogu($this->projectRepository, $this->securityService);
    }

    /**
     * Добавляет в проект обычного участника (роль `USER`).
     */
    protected function dobavitUchastnika(string $userId, string $projectId = self::PROJECT_ID): void
    {
        $this->projectRepository
            ->getById($projectId)
            ->addUser(new Account($userId, $userId . '@test.ru', false));
    }

    protected function sozdatProekt(string $projectId, string $adminId): Project
    {
        return Project::addProject(
            $projectId,
            new AddProject('repo-1', 'code-' . $projectId, 'project-' . $projectId, 'master', 'https://test.ru', 1000),
            $adminId
        );
    }

    protected function dobavitTip(string $code, string $name = 'Тип тома', string $projectId = self::PROJECT_ID): VolumeType
    {
        $type = new VolumeType($this->guidGenerator->makeGuid(), $projectId, $code, $name, 1000);
        $this->typeRepository->save($type);

        return $type;
    }

    /**
     * Создаёт тип и привязывает к нему проектный архив.
     */
    protected function tipSFaylom(string $code, string $fileId, string $projectId = self::PROJECT_ID): VolumeType
    {
        $type = $this->dobavitTip($code, 'Тип ' . $code, $projectId);
        $this->mediatekaService->vladelcy[$fileId] = ['unitId' => null, 'projectId' => $projectId];
        $this->storage->links[] = new VolumeTypeFile($type->id, $projectId, $fileId, 1000);

        return $type;
    }
}
