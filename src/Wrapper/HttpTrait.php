<?php

namespace Laravoole\Wrapper;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HttpTrait
{
    protected $accept_gzip = false;
    protected function handleResponse($response, $illuminateResponse, $accept_encoding = '')
    {

        $accept_gzip = $this->accept_gzip && stripos($accept_encoding, 'gzip') !== false;

        // status
        $response->status($illuminateResponse->getStatusCode());
        // headers
        $response->header('Server', config('laravoole.base_config.server'));
        foreach ($illuminateResponse->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($illuminateResponse->headers->getCookies() as $cookie) {
            $response->rawcookie(
                $cookie->getName(),
                urlencode($cookie->getValue()),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
        // content
        if ($illuminateResponse instanceof BinaryFileResponse) {
            $content = function () use ($illuminateResponse) {
                return $illuminateResponse->getFile()->getPathname();
            };
            if ($accept_gzip && isset($response->header['Content-Type'])) {
            	$size = $illuminateResponse->getFile()->getSize();
            }
        } else {
            $content = $illuminateResponse->getContent();
            // check gzip
            if ($accept_gzip && isset($response->header['Content-Type'])) {
                $mime = $response->header['Content-Type'];

                if (strlen($content) > config('laravoole.base_config.gzip_min_length') && is_mime_gzip($mime)) {
                    $response->gzip(config('laravoole.base_config.gzip'));
                }
            }
        }
        return $this->endResponse($response, $content);
    }

}
