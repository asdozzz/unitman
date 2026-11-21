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

    /**
     * @param string $mainBranch основная ветка проекта: нужна, чтобы отличить
     *                            push в неё от push в ветку юнита и корректно
     *                            определить факт слияния.
     */
    function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya;
}
