<?php

namespace App\Utils\Converter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class JsonBodySerializableConverter implements ValueResolverInterface
{
    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($argument)) {
            return [];
        }

        $obj = $this->serializer->deserialize($request->getContent(), $argument->getType() ?? '', 'json',[
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false
        ]);

        $request->attributes->set($argument->getName(), $obj);

        return [$obj];
    }

    public function supports(ArgumentMetadata $argument): bool
    {
        $class = $argument->getType();
        if (empty($class)) {
            return false;
        }
        $class_implements = class_implements($class);
        /** @var array $class_implements*/
        return in_array(\App\Utils\Converter\JsonBodySerializableInterface::class, $class_implements);
    }
}
