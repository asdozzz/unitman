<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;

/**
 * Подмена Unitman'а для API-тестов: состояния и доступ задаются тестом.
 *
 * Файл намеренно не оканчивается на `Test.php`, поэтому PHPUnit не собирает
 * его как тест-класс (см. `suffix="Test.php"` в `phpunit.xml.dist`).
 *
 * Неизвестный юнит считается несуществующим и недоступным — так же, как его
 * увидел бы реальный адаптер над публичным API Unitman.
 */
final class ImitaciyaUpravleniyaUnitami implements UmeetUpravlyatUnitami
{
    /**
     * @var array<string, SostoyanieUnita>
     */
    private array $sostoyaniya = [];

    /**
     * @var array<string, bool>
     */
    private array $dostup = [];

    /**
     * @var list<array{unitId: string, accountId: string}>
     */
    private array $prodvizheniya = [];

    private ?ParametriSozdaniyaUnita $poslednieParametri = null;

    private ?string $posledniiAccountIdSozdaniya = null;

    public function zadatSostoyanieUnita(string $unitId, SostoyanieUnita $sostoyanie, bool $dostupen = true): void
    {
        $this->sostoyaniya[$unitId] = $sostoyanie;
        $this->dostup[$unitId] = $dostupen;
    }

    public function poluchitSostoyanieUnita(string $unitId): SostoyanieUnita
    {
        return $this->sostoyaniya[$unitId]
            ?? new SostoyanieUnita(false, null, null, null, null, SostoyanieUnita::GROUP_ARCHIVED);
    }

    public function proveritDostupKUnitu(string $unitId, string $accountId): bool
    {
        return $this->dostup[$unitId] ?? false;
    }

    public function sozdatUnit(ParametriSozdaniyaUnita $parametri, string $accountId): string
    {
        $this->poslednieParametri = $parametri;
        $this->posledniiAccountIdSozdaniya = $accountId;

        return 'unit-' . $parametri->unitName;
    }

    /**
     * @param list<array{projectId: string, branch: string}> $projects
     *
     * @return list<SozdanniyUnit>
     */
    public function sozdatUnitiDlyaProektov(
        array $projects,
        string $unitName,
        string $accountId,
    ): array {
        $this->posledniiAccountIdSozdaniya = $accountId;

        $result = [];

        foreach ($projects as $project) {
            $result[] = new SozdanniyUnit(
                'unit-' . $unitName . '-' . $project['projectId'],
                $project['projectId'],
                $unitName,
                $project['branch'],
            );
        }

        return $result;
    }

    public function prodvinutCiklUnita(string $unitId, string $accountId): void
    {
        $this->prodvizheniya[] = ['unitId' => $unitId, 'accountId' => $accountId];
    }

    public function poslednieParametriSozdaniya(): ?ParametriSozdaniyaUnita
    {
        return $this->poslednieParametri;
    }

    public function posledniiAccountIdSozdaniya(): ?string
    {
        return $this->posledniiAccountIdSozdaniya;
    }

    /**
     * @return list<array{unitId: string, accountId: string}>
     */
    public function prodvizheniya(): array
    {
        return $this->prodvizheniya;
    }
}
