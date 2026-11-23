<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use Psr\Log\LoggerInterface;

/**
 * Немедленное применение удержанных намерений после включения автообновления.
 *
 * Фоновый обход тоже умеет освобождать такие строки, но пользователь ждёт
 * реакции сразу после сохранения настроек. Этот сценарий не заменяет
 * страховочный job, а дополняет его.
 */
final class PrimenitOtlozhennieDeistviyaPoProektuUseCase
{
    public function __construct(
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase $primenitDlyaUnita,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(string $projectId): void
    {
        $now = new \DateTimeImmutable();
        $this->otlozhennieRepository->osvoboditUderzhannie($projectId, $now);

        foreach ($this->otlozhennieRepository->poluchitUnitIdsSPoGotovimi($projectId, $now) as $unitId) {
            try {
                $this->primenitDlyaUnita->handle($unitId);
            } catch (\Throwable $e) {
                /*
                 * Один юнит не должен мешать остальным. Намерение осталось
                 * в таблице, поэтому фоновый обход повторит попытку.
                 */
                $this->logger->error('unit.otlozhennie_deistvie.primenenie_po_proektu_upalo', [
                    'projectId' => $projectId,
                    'unitId' => $unitId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
