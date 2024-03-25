<?php

namespace App\Utils\Service;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SerializerFactory
{
    public function __construct(private ClassMetadataFactoryInterface $classMetadataFactory)
    {
    }

    public function __invoke(): Serializer
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $objectNormalizer = new ObjectNormalizer(
            classMetadataFactory: $this->classMetadataFactory,
            propertyTypeExtractor: $extractor
        );
        $normalizers = [new BackedEnumNormalizer(), $objectNormalizer, new ArrayDenormalizer()];
        return new Serializer($normalizers,[new JsonEncoder()]);
    }
}
