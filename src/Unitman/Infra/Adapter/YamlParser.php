<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Port\CanParseYaml;
use Symfony\Component\Yaml\Yaml;

final class YamlParser implements CanParseYaml
{
    function parse(string $content): array
    {
        return Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }
}
