<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;

/**
 * Подмена жизненного цикла workspace на стороне Runner.
 *
 * Записывает операции и позволяет задать результат проверки/операции.
 * Файл не оканчивается на `Test.php`, поэтому PHPUnit не собирает его как тест.
 */
final class ImitaciyaUpravleniyaWorkspace implements UmeetUpravlyatWorkspace
{
    /**
     * @var list<array{operation: string, workspaceId: string}>
     */
    private array $operacii = [];

    /**
     * @var list<array{workspaceId: string, update: WorkspacePendingUpdate}>
     */
    private array $obnovleniya = [];

    private string $workflowObnovleniya = 'wf-update';

    private ?\Throwable $oshibkaObnovleniya = null;

    private ?string $rezultatOperacii = null;

    /**
     * Задаёт workflow id, который вернёт obnovit().
     */
    public function zadatWorkflowObnovleniya(string $workflowId): void
    {
        $this->workflowObnovleniya = $workflowId;
    }

    /**
     * Заставляет obnovit() выбросить исключение.
     */
    public function zadatOshibkuObnovleniya(?\Throwable $error): void
    {
        $this->oshibkaObnovleniya = $error;
    }

    /**
     * @return list<array{workspaceId: string, update: WorkspacePendingUpdate}>
     */
    public function obnovleniya(): array
    {
        return $this->obnovleniya;
    }

    private bool $uspehProverki = true;

    /**
     * @var list<array{projectId: string, name: string, dirty: bool, files: list<string>}>
     */
    private array $izmeneniya = [];

    public function zadatRezultatOperacii(?string $result): void
    {
        $this->rezultatOperacii = $result;
    }

    public function zadatRezultatProverki(bool $success, array $izmeneniya = []): void
    {
        $this->uspehProverki = $success;
        $this->izmeneniya = $izmeneniya;
    }

    public function zapustit(SessiyaOpenCode $session): void
    {
        $this->zapisat(Workspace::OPERATION_CREATE, $session, 'wf-create');
    }

    public function obnovit(SessiyaOpenCode $session, WorkspacePendingUpdate $update): string
    {
        if ($this->oshibkaObnovleniya !== null) {
            throw $this->oshibkaObnovleniya;
        }

        $this->obnovleniya[] = ['workspaceId' => $session->getId(), 'update' => $update];
        $this->zapisat(Workspace::OPERATION_UPDATE, $session, $this->workflowObnovleniya);

        return $this->workflowObnovleniya;
    }

    public function ostanovit(SessiyaOpenCode $session): void
    {
        $this->zapisat(Workspace::OPERATION_STOP, $session, 'wf-stop');
    }

    public function vosstanovit(SessiyaOpenCode $session): void
    {
        $this->zapisat(Workspace::OPERATION_START, $session, 'wf-start');
    }

    public function udalit(SessiyaOpenCode $session): void
    {
        $this->zapisat(Workspace::OPERATION_DELETE, $session, 'wf-delete');
    }

    public function poluchitIzmeneniya(SessiyaOpenCode $session): RezultatProverkiIzmeneniyWorkspace
    {
        return new RezultatProverkiIzmeneniyWorkspace($this->uspehProverki, $this->izmeneniya);
    }

    public function poluchitRezultatOperacii(SessiyaOpenCode $session): ?string
    {
        return $this->rezultatOperacii;
    }

    /**
     * @return list<array{operation: string, workspaceId: string}>
     */
    public function operacii(): array
    {
        return $this->operacii;
    }

    private function zapisat(string $operation, SessiyaOpenCode $session, string $workflowId): void
    {
        $this->operacii[] = ['operation' => $operation, 'workspaceId' => $session->getId()];
        $session->getWorkspace()?->zapomnitOperaciyu($operation, $workflowId, time());
    }
}
