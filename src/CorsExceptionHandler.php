<?php

declare(strict_types=1);

namespace Gokure\HyperfCors;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class CorsExceptionHandler extends ExceptionHandler
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $request = $this->container->get(RequestInterface::class);
            return $this->container->get(Cors::class)->addActualRequestHeaders($response, $request);
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
