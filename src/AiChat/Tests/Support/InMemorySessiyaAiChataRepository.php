<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;

/**
 * In-memory реализация хранилища сессий для юнит-тестов use case.
 *
 * Хранит доменные объекты по ссылке и записывает порядок вызовов, чтобы
 * проверить автоархивацию перед выборкой списка.
 */
final class InMemorySessiyaAiChataRepository implements SessiyaAiChataRepository
{
    /**
     * @var array<string, SessiyaAiChata>
     */
    public array $sessii = [];

    /**
     * @var list<string>
     */
    public array $vyzovy = [];

    public int $sohraneniy = 0;

    public ?string $arhivirovatIstekchieAccountId = null;

    public ?int $arhivirovatIstekchieNow = null;

    public ?string $spisokAccountId = null;

    public ?FiltrSessiyAiChata $spisokFiltr = null;

    public ?SpisokSessiyAiChataReadModel $spisokRezultat = null;

    public ?SessiyaAiChata $udalenna = null;

    public function naytiPoId(string $id): ?SessiyaAiChata
    {
        return $this->sessii[$id] ?? null;
    }

    public function naytiPoIdDlyaObnovleniya(string $id): ?SessiyaAiChata
    {
        return $this->naytiPoId($id);
    }

    public function sohranit(SessiyaAiChata $sessiya): void
    {
        ++$this->sohraneniy;
        $this->sessii[$sessiya->id()] = $sessiya;
    }

    public function spisok(string $accountId, FiltrSessiyAiChata $filtr): SpisokSessiyAiChataReadModel
    {
        $this->vyzovy[] = 'spisok';
        $this->spisokAccountId = $accountId;
        $this->spisokFiltr = $filtr;

        return $this->spisokRezultat
            ?? new SpisokSessiyAiChataReadModel([], $filtr->limit, $filtr->offset, 0);
    }

    public function arhivirovatIstekchie(string $accountId, int $now): int
    {
        $this->vyzovy[] = 'arhivirovatIstekchie';
        $this->arhivirovatIstekchieAccountId = $accountId;
        $this->arhivirovatIstekchieNow = $now;

        return 0;
    }

    public function udalit(SessiyaAiChata $sessiya): void
    {
        $this->udalenna = $sessiya;
        unset($this->sessii[$sessiya->id()]);
    }
}
