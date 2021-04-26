<?php

declare(strict_types=1);

namespace Gokure\HyperfCors;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var Cors
     */
    protected $cors;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(Cors $cors, ContainerInterface $container)
    {
        $this->cors = $cors;
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if we're dealing with CORS and if we should handle it
        if (! $this->shouldRun($request)) {
            return $handler->handle($request);
        }

        // For Preflight, return the Preflight response
        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);

            return $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        // Handle the request
        $response = $handler->handle($request);

        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->addHeaders($request, $response);
    }

    /**
     * Add the headers to the Response, if they don't exist yet.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function addHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $this->cors->addActualRequestHeaders($response, $request);
        }

        return $response;
    }

    /**
     * Determine if the request has a URI that should pass through the CORS flow.
     *
     * @param  ServerRequestInterface  $request
     * @return bool
     */
    protected function shouldRun(ServerRequestInterface $request): bool
    {
        return $this->isMatchingPath($request);
    }

    /**
     * The the path from the config, to see if the CORS Service should run
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isMatchingPath(ServerRequestInterface $request): bool
    {
        // Get the paths from the config or the middleware
        $paths = $this->getPathsByHost($request->getUri()->getHost());

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            $url = (string)$request->getUri();

            if (Str::is($path, $url) || Str::is($path, rawurldecode($url))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paths by given host or string values in config by default
     *
     * @param string $host
     * @return array
     */
    protected function getPathsByHost(string $host)
    {
        $paths = $this->container->get(ConfigInterface::class)->get('cors.paths', []);
        // If where are paths by given host
        return $paths[$host] ?? array_filter($paths, function ($path) {
                return is_string($path);
            });
    }
}
