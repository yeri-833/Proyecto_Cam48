<?php

namespace Tests\Isolation;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class IsolatedHttpFactory extends Factory
{
    protected function newPendingRequest(): PendingRequest
    {
        return parent::newPendingRequest()->setHandler(
            static function (RequestInterface $request, array $options): never {
                throw new RuntimeException('ISOLATION_HTTP_TRANSPORT_FORBIDDEN');
            },
        );
    }
}
