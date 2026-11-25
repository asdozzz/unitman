<?php

declare(strict_types=1);

namespace App\Notification\Infra\Jobs;

use App\Notification\Business\Exception\PushPodpiskaNedeystvitelnaException;
use App\Notification\Business\Model\PushPodpiska;
use App\Notification\Business\Model\StatusDostavki;
use App\Notification\Business\Port\DostavkiRepository;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\PushPodpiskiRepository;
use App\Notification\Business\Port\UmeetOtpravlyatPush;
use App\Notification\Business\Port\UvedomleniyaRepository;
use App\Notification\Business\UseCase\SozdatUvedomlenieUseCase;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

final class NotificationPushJobHandler implements JobsHandlerInterface
{
    public const QUEUE_NAME = 'notification_push';
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly UvedomleniyaRepository $uvedomleniya,
        private readonly PushPodpiskiRepository $podpiski,
        private readonly NastroikiUvedomleniyRepository $nastroiki,
        private readonly DostavkiRepository $dostavki,
        private readonly UmeetOtpravlyatPush $otpravitel,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $payload = json_decode($task->getPayload(), true);
        $notificationId = is_array($payload) ? (string) ($payload['notificationId'] ?? '') : '';

        if ($notificationId === '') {
            return;
        }

        $uvedomlenie = $this->uvedomleniya->findById($notificationId);

        if ($uvedomlenie === null) {
            return;
        }

        $delivery = $this->dostavki->find($notificationId, SozdatUvedomlenieUseCase::PUSH_CHANNEL);

        if ($delivery === null || $delivery->getState() === StatusDostavki::SENT) {
            return;
        }

        $recipients = $this->uvedomleniya->findPoluchateleyIds($notificationId);

        if ($recipients !== [] && !$uvedomlenie->getType()->sistemniy()) {
            $otkluchenni = $this->nastroiki->findOtkluchennih($uvedomlenie->getType(), $recipients);

            if ($otkluchenni !== []) {
                $recipients = array_values(array_diff($recipients, $otkluchenni));
            }
        }

        $now = time();

        if ($recipients === []) {
            $this->dostavki->markSent($notificationId, SozdatUvedomlenieUseCase::PUSH_CHANNEL, $now);

            return;
        }

        $subscriptions = $this->podpiski->findDlyaAccounts($recipients);

        if ($subscriptions === []) {
            $this->dostavki->markSent($notificationId, SozdatUvedomlenieUseCase::PUSH_CHANNEL, $now);

            return;
        }

        $errors = [];

        /** @var PushPodpiska $podpiska */
        foreach ($subscriptions as $podpiska) {
            try {
                $this->otpravitel->otpravit($uvedomlenie, $podpiska);
            } catch (PushPodpiskaNedeystvitelnaException) {
                $this->podpiski->deleteById($podpiska->getId());
            } catch (\Throwable $error) {
                $errors[] = $error->getMessage();
                $this->logger->error('Не удалось отправить Web Push уведомление.', [
                    'notificationId' => $notificationId,
                    'subscriptionId' => $podpiska->getId(),
                    'exception' => $error,
                ]);
            }
        }

        if ($errors === []) {
            $this->dostavki->markSent($notificationId, SozdatUvedomlenieUseCase::PUSH_CHANNEL, $now);

            return;
        }

        $errorText = implode('; ', array_slice($errors, 0, 3));

        if ($delivery->getAttempts() + 1 >= self::MAX_ATTEMPTS) {
            $this->dostavki->markFailed(
                $notificationId,
                SozdatUvedomlenieUseCase::PUSH_CHANNEL,
                $now,
                $errorText,
                self::MAX_ATTEMPTS,
            );

            return;
        }

        $this->dostavki->markPending(
            $notificationId,
            SozdatUvedomlenieUseCase::PUSH_CHANNEL,
            $errorText,
        );
    }
}
