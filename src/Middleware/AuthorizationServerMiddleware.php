<?php

declare(strict_types=1);
/*
 * PHP version 7.1
 *
 * @copyright Copyright (c) 2012-2017 EELLY Inc. (https://www.eelly.com)
 * @link      https://api.eelly.com
 * @license   衣联网版权所有
 */

namespace Eelly\OAuth2\Server\Middleware;

use Eelly\OAuth2\Server\ClientCredentialsAuthorizationServer;
use Eelly\OAuth2\Server\Middleware\Traits\ResponseTrait;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

/**
 * @author hehui<hehui@eelly.net>
 */
class AuthorizationServerMiddleware
{
    use ResponseTrait;

    private $cryptKeyPath;

    public function __construct(string $cryptKeyPath)
    {
        $this->cryptKeyPath = $cryptKeyPath;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param callable          $next
     *
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $psr7Response = new Response();
        try {
            $grantType = $request->getPost('grant_type');
            $server = $this->getAuthorizationServer($grantType);
            $psr7Request = ServerRequest::fromGlobals();
            $psr7Response = $server->respondToAccessTokenRequest($psr7Request, $psr7Response);
            $this->convertResponse($psr7Response, $response);
        } catch (OAuthServerException $exception) {
            $psr7Response = $exception->generateHttpResponse($psr7Response);

            return $this->convertResponse($psr7Response, $response);
            // @codeCoverageIgnoreStart
        } catch (\Exception $exception) {
            $psr7Response = (new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500))
                ->generateHttpResponse($psr7Response);

            return $this->convertResponse($psr7Response, $response);
            // @codeCoverageIgnoreEnd
        }

        // Pass the request and response on to the next responder in the chain
        return $next($request, $response);
    }

    private function getAuthorizationServer($grantType)
    {
        switch ($grantType) {
            case 'client_credentials':
                $server = new ClientCredentialsAuthorizationServer($this->cryptKeyPath);
                break;
            default:
                throw OAuthServerException::unsupportedGrantType();
        }

        return $server;
    }
}
