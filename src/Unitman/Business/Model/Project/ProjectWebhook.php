<?php

namespace App\Unitman\Business\Model\Project;

use App\Unitman\Business\Command\Project\Webhook\SozdatWebhookProekta;

final class ProjectWebhook
{
    function __construct(
        public readonly string $id,
        public readonly string $projectId,
        private string $url,
        private bool $active,
        private ?\DateTimeImmutable $dataUdaleniya
    )
    {
    }

    static function sozdat(string $id, SozdatWebhookProekta $command): self
    {
        if (empty($id)) {
            throw new \DomainException('project.webhook.id_is_empty');
        }

        if (empty($command->projectId)) {
            throw new \DomainException('project.webhook.projectId_is_empty');
        }

        if (empty($command->url)) {
            throw new \DomainException('project.webhook.url_is_empty');
        }

        self::validateUrl($command->url);

        return new self($id, $command->projectId, $command->url, true, null);
    }

    /**
     * @param string $url
     * @return void
     */
    private static function validateUrl(string $url): void
    {
        if (!preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}' . '((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
            throw new \DomainException('project.webhook.url_invalid');
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isDeleted(): bool
    {
        return !empty($this->dataUdaleniya);
    }

    public function getDataUdaleniya(): ?\DateTimeImmutable
    {
        return $this->dataUdaleniya;
    }

    function otkluchit(): void
    {
        $this->active = false;
    }

    function vkluchit(): void
    {
        $this->active = true;
    }

    function udalit(\DateTimeImmutable $dateTimeImmutable): void
    {
        $this->dataUdaleniya = $dateTimeImmutable;
        $this->active = false;
    }

    function izmenitUrl(string $newUrl): void
    {
        if ($this->url === $newUrl) {
            throw new \DomainException('project.webhook.new_url_equals_old_url');
        }
        self::validateUrl($newUrl);
        $this->url = $newUrl;
    }
}
