<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.storage_type_for_hook_adapter')]
interface StorageTypeAdapter
{
    public function esliValidnoeSobitie(SobitiePayload $payload): bool;
    public function isSupport(RepoType $repoType): bool;
    function poluchitDannieSobitiya(SobitiePayload $payload): DannieSobitiya;
}
