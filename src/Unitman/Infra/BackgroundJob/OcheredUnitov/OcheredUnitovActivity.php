<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredUnitov;

use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class OcheredUnitovActivity
{
    public function __construct(
        private OcheredUnitovRepository $ocheredUnitovRepository,
        private ObrabotatProzesiUnitaUseCase $obrabotatProzesiUnitaUseCase,
        private Connection $connection,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * Обрабатывает пачку очереди в одной транзакции.
     *
     * Зачем транзакция. `SELECT ... FOR UPDATE SKIP LOCKED` удерживает
     * блокировки строк только до конца транзакции. В autocommit транзакция
     * заканчивается вместе с SELECT, блокировки снимаются мгновенно, и все
     * 10 процессов очереди выбирают одни и те же строки — SKIP LOCKED
     * не защищает ни от чего (ровно это и наблюдалось: одна строка,
     * выбранная десятью воркерами). Здесь транзакция охватывает и выборку,
     * и обработку, поэтому строки заняты до конца пачки.
     *
     * Каждая запись обрабатывается во вложенной транзакции (savepoints
     * включены в doctrine.yaml): падение на одном юните откатывает только
     * его, не ломая уже сделанную работу остальных.
     *
     * @return int сколько записей разобрано
     */
    public function obrabotatOchered(int $limit = 100): int
    {
        $this->ocheredUnitovRepository->init();

        $this->connection->beginTransaction();

        try {
            $zadachi = $this->ocheredUnitovRepository->poluchitZadachiNaObrabotku($limit);

            foreach ($zadachi as $zadacha) {
                $this->obrabotatZadachu($zadacha);
            }

            $this->connection->commit();

            return count($zadachi);
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Обрабатывает одну запись очереди.
     *
     * Вложенная транзакция даёт savepoint: ошибка одного юнита откатывает
     * только его изменения, пачка продолжается.
     *
     * Удаляем из очереди, когда у юнита не осталось незавершённых заданий.
     * Без этого «пустой» юнит не порождает событий, проекция не получает
     * повода удалить строку, и запись опрашивается вечно.
     */
    public function obrabotatZadachu(OcheredUnitovReadModel $model): void
    {
        $this->connection->beginTransaction();

        try {
            $estRabota = $this->obrabotatProzesiUnitaUseCase->handle($model->unitId);

            if (!$estRabota) {
                // Юнит терминален: снимаем его с опроса. Строка уже под
                // FOR UPDATE этого процесса, поэтому удаляем по unit_id
                // идемпотентно.
                $this->ocheredUnitovRepository->removeByUnitId($model->unitId);

                $this->logger->debug('ochered_unitov.unit_is_terminal_removed', [
                    'unit_id' => $model->unitId,
                    'task_id' => $model->id,
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
