<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;

/**
 * Антикоррупционный слой над модулем Runner.
 *
 * Модуль OpenCode не знает про Temporal и про DTO раннера: он отдаёт
 * «намерение выполнить ход» и получает результат в собственной модели.
 */
final class RunnerAdapter implements UmeetVypolnyatHodOpenCode
{
    public function __construct(private RunnerApi $runnerApi)
    {
    }

    public function nachatHod(
        string $workflowId,
        string $accountId,
        string $credentialId,
        ?string $sessionId,
        string $prompt,
        array $answers,
        array $history,
    ): string {
        return $this->runnerApi->nachatHodOpenCode(
            $workflowId,
            new NachatHodOpenCode(
                $accountId,
                $credentialId,
                $sessionId,
                $prompt,
                $answers,
                $history,
            ),
        );
    }

    public function kanalProgressa(string $workflowId): string
    {
        return $this->runnerApi->poluchitKanalProgressa($workflowId);
    }

    public function poluchitResultatHoda(string $workflowId): ?RezultatHodaOpenCode
    {
        $result = $this->runnerApi->poluchitResultatHodaOpenCode($workflowId);

        return $result === null ? null : $this->prevratit($result);
    }

    public function poluchitProgressHoda(string $workflowId): ?ProgressHodaOpenCode
    {
        $progress = $this->runnerApi->poluchitProgressZadachi($workflowId);

        if ($progress === null) {
            return null;
        }

        return new ProgressHodaOpenCode(
            $progress->steps,
            $progress->finished,
            $progress->success,
            $progress->unixtime,
        );
    }

    private function prevratit(ResultatHodaOpenCode $result): RezultatHodaOpenCode
    {
        return new RezultatHodaOpenCode(
            $result->Success,
            $result->Kind,
            $this->prevratitVoprosy($result->Questions),
            $result->Answer,
            $result->ErrorKey,
            $result->ExternalSessionId,
        );
    }

    /**
     * Приведение вопросов раннера к форме доменной модели OpenCode.
     *
     * В контракте раннера `Description` необязателен (`Description?: string`),
     * доменная модель же гарантирует, что ключ есть всегда (`string|null`).
     * Без явного приведения отсутствующее описание превратилось бы в
     * несуществующий ключ, и JSON одного и того же ответа отличался бы по форме.
     *
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description?: string}>
     * }> $questions
     *
     * @return list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }>
     */
    private function prevratitVoprosy(array $questions): array
    {
        $preobrazovannye = [];

        foreach ($questions as $question) {
            $options = [];

            foreach ($question['Options'] as $option) {
                $description = $option['Description'] ?? null;

                $options[] = [
                    'Id' => $option['Id'],
                    'Label' => $option['Label'],
                    'Description' => $description === '' ? null : $description,
                ];
            }

            $preobrazovannye[] = [
                'Id' => $question['Id'],
                'Text' => $question['Text'],
                'AllowFreeText' => $question['AllowFreeText'],
                'Options' => $options,
            ];
        }

        return $preobrazovannye;
    }
}