<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.storage_type_for_hook_adapter')]
interface StorageTypeAdapter
{
    public function esliValidnoeSobitie(SobitieIzHranilisha $sobitieIzHranilisha): bool;
    public function isSupport(RepoType $repoType): bool;
    function poluchitDannieSobitiya(SobitieIzHranilisha $sobitieIzHranilisha): DannieSobitiya;
}
