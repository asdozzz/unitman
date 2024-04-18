<?php

namespace App\App\Infra\Workflow;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\Converter;
use Temporal\DataConverter\Type;
use Temporal\Exception\DataConverterException;

final class SymfonyConverter extends Converter
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function getEncodingType(): string
    {
        return 'json/object';
    }

    public function toPayload(mixed $value): ?Payload
    {
        if (!\is_object($value)) {
            return null;
        }

        return $this->create(
            $this->serializer->serialize($value, 'json')
        );
    }

    public function fromPayload(Payload $payload, Type $type): object
    {
        if (!$type->isClass()) {
            throw new DataConverterException('Unable to decode value using object converter.');
        }

        try {
            return $this->serializer->deserialize($payload->getData(), $type->getName(), 'json');
        } catch (\Exception $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
