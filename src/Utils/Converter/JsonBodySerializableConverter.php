<?php

namespace App\Utils\Converter;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class JsonBodySerializableConverter implements ParamConverterInterface
{
    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $body = $request->getContent();

        if (empty($body))
        {
            $body = '';
        }

        $obj = $this->serializer->deserialize($body, $configuration->getClass() ?? '', 'json',[
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false
        ]);

        $request->attributes->set($configuration->getName(), $obj);

        return true;
    }

    public function supports(ParamConverter $configuration)
    {
        $class = $configuration->getClass();
        if (empty($class)) {
            return false;
        }
        $class_implements = class_implements($class);
        /** @var array $class_implements*/
        return in_array(\App\Utils\Converter\JsonBodySerializableInterface::class, $class_implements);
    }
}
