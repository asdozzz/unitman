<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Model\LichniyToken\Event\LichniyTokenDobavlen;
use App\Unitman\Business\Model\LichniyToken\Event\LichniyTokenUdalen;
use App\Unitman\Business\Model\LichniyToken\Event\PravaLichnogoTokenaObnovleni;
use App\Unitman\Business\Model\LichniyToken\LichniyTokenId;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

/**
 * Персональный токен хранилища (GitLab/GitHub) пользователя.
 *
 * Секрет хранится только в зашифрованном виде; агрегат не умеет шифровать —
 * это делает UseCase через порт `UmeetShifrovatSekrety`.
 *
 * @template-implements AggregateRoot<LichniyTokenId>
 */
final class LichniyToken implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<LichniyTokenId>
     */
    use AggregateRootBehaviour;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $accountId;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $repoId;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $nazvanie;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $tokenCipher;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private string $tokenHint;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ObyomPrav $obyomPrav;
    /**
     * @var string[]
     */
    private array $proektySPushom = [];
    /** @psalm-suppress PropertyNotSetInConstructor */
    private int $proverenoV;
    private bool $isDeleted = false;

    /**
     * @param string[] $proektySPushom
     */
    public static function dobavit(
        string $id,
        string $accountId,
        string $repoId,
        string $nazvanie,
        string $tokenCipher,
        string $tokenHint,
        ObyomPrav $obyomPrav,
        array $proektySPushom,
        int $now,
    ): self {
        if ($accountId === '') {
            throw new \DomainException('lichniy_token.vladelec_ne_ukazan');
        }

        if ($repoId === '') {
            throw new \DomainException('lichniy_token.repo_ne_ukazan');
        }

        $nazvanie = trim($nazvanie);

        if ($nazvanie === '') {
            throw new \DomainException('lichniy_token.pustoe_nazvanie');
        }

        if ($tokenCipher === '') {
            throw new \DomainException('lichniy_token.pustoy_token');
        }

        $token = new self(LichniyTokenId::fromString($id));
        $token->recordThat(new LichniyTokenDobavlen(
            $id,
            $accountId,
            $repoId,
            $nazvanie,
            $tokenCipher,
            $tokenHint,
            $obyomPrav->value,
            self::normalizeProekty($proektySPushom),
            $now,
        ));

        return $token;
    }

    private function applyLichniyTokenDobavlen(LichniyTokenDobavlen $fact): void
    {
        $this->accountId = $fact->accountId;
        $this->repoId = $fact->repoId;
        $this->nazvanie = $fact->nazvanie;
        $this->tokenCipher = $fact->tokenCipher;
        $this->tokenHint = $fact->tokenHint;
        $this->obyomPrav = ObyomPrav::from($fact->obyomPrav);
        $this->proektySPushom = $fact->proektySPushom;
        $this->proverenoV = $fact->proverenoV;
    }

    public function udalit(): void
    {
        if ($this->isDeleted) {
            return;
        }

        $this->recordThat(new LichniyTokenUdalen($this->getId()));
    }

    private function applyLichniyTokenUdalen(LichniyTokenUdalen $fact): void
    {
        $this->isDeleted = true;
    }

    /**
     * @param string[] $proektySPushom
     */
    public function obnovitPrava(ObyomPrav $obyomPrav, array $proektySPushom, int $now): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('lichniy_token.ne_nayden');
        }

        $this->recordThat(new PravaLichnogoTokenaObnovleni(
            $this->getId(),
            $obyomPrav->value,
            self::normalizeProekty($proektySPushom),
            $now,
        ));
    }

    private function applyPravaLichnogoTokenaObnovleni(PravaLichnogoTokenaObnovleni $fact): void
    {
        $this->obyomPrav = ObyomPrav::from($fact->obyomPrav);
        $this->proektySPushom = $fact->proektySPushom;
        $this->proverenoV = $fact->proverenoV;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    public function trebovatVladelca(string $accountId): void
    {
        if (!$this->prinadlezhit($accountId)) {
            throw new \DomainException('lichniy_token.chuzhoy');
        }
    }

    public function pokryvaetProekt(string $projectId): bool
    {
        if ($this->isDeleted) {
            return false;
        }

        if ($this->obyomPrav === ObyomPrav::Vse) {
            return true;
        }

        return in_array($projectId, $this->proektySPushom, true);
    }

    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getRepoId(): string
    {
        return $this->repoId;
    }

    public function getNazvanie(): string
    {
        return $this->nazvanie;
    }

    public function getTokenCipher(): string
    {
        return $this->tokenCipher;
    }

    public function getTokenHint(): string
    {
        return $this->tokenHint;
    }

    public function getObyomPrav(): ObyomPrav
    {
        return $this->obyomPrav;
    }

    /**
     * @return string[]
     */
    public function getProektySPushom(): array
    {
        return $this->proektySPushom;
    }

    public function getProverenoV(): int
    {
        return $this->proverenoV;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    /**
     * AAD для AES-GCM: привязывает шифротекст к владельцу и записи.
     */
    public static function makeAad(string $accountId, string $tokenId): string
    {
        return $accountId . ':' . $tokenId;
    }

    /**
     * Маска для показа: последние 4 символа, остальное скрыто.
     */
    public static function makeHint(string $secret): string
    {
        $length = \strlen($secret);

        if ($length === 0) {
            return '';
        }

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return '****' . substr($secret, -4);
    }

    /**
     * @param string[] $proekty
     * @return string[]
     */
    private static function normalizeProekty(array $proekty): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (string $id): string => trim($id), $proekty),
            static fn (string $id): bool => $id !== '',
        )));
    }
}
