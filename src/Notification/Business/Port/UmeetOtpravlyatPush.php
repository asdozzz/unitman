<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

use App\Notification\Business\Exception\PushPodpiskaNedeystvitelnaException;
use App\Notification\Business\Model\PushPodpiska;
use App\Notification\Business\Model\Uvedomlenie;

interface UmeetOtpravlyatPush
{
    /**
     * @throws PushPodpiskaNedeystvitelnaException подписка больше не действительна
     */
    public function otpravit(Uvedomlenie $uvedomlenie, PushPodpiska $podpiska): void;
}
