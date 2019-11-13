<?php
namespace unapi\sms\smsarea;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;

interface ParserInterface extends LoggerAwareInterface
{
    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseServicePhone(ResponseInterface $response): PromiseInterface;

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseDeclinePhoneResult(ResponseInterface $response): PromiseInterface;

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseReadyPhoneResult(ResponseInterface $response): PromiseInterface;

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseConfirmPhoneResult(ResponseInterface $response): PromiseInterface;

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseStateResponse(ResponseInterface $response): PromiseInterface;

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseBalance(ResponseInterface $response): PromiseInterface;
}