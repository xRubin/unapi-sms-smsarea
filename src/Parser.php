<?php
namespace unapi\sms\smsarea;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use unapi\sms\common\dto\ServicePhoneInterface;
use unapi\sms\smsarea\dto\StateResponseInterface;

class Parser implements ParserInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $servicePhoneClass = dto\ServicePhoneDto::class;
    /** @var string */
    private $stateResponseClass = dto\StateResponseDto::class;

    /**
     * @param array $config Parser configuration settings.
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['logger'])) {
            $this->logger = new NullLogger();
        } elseif ($config['logger'] instanceof LoggerInterface) {
            $this->setLogger($config['logger']);
        } else {
            throw new \InvalidArgumentException('Logger must be instance of LoggerInterface');
        }

        if (isset($config['servicePhoneClass'])) {
            if ($config['servicePhoneClass'] instanceof ServicePhoneInterface) {
                $this->servicePhoneClass = $config['servicePhoneClass'];
            } else {
                throw new \InvalidArgumentException('servicePhoneClass must implement unapi\sms\common\dto\ServicePhoneInterface');
            }
        }

        if (isset($config['stateResponseClass'])) {
            if ($config['stateResponseClass'] instanceof StateResponseInterface) {
                $this->stateResponseClass = $config['stateResponseClass'];
            } else {
                throw new \InvalidArgumentException('stateResponseClass must implement unapi\sms\smsarea\dto\StateResponseInterface');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseServicePhone(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        $parts = explode(':', $data);
        switch ($parts[0]) {
            case 'ACCESS_NUMBER':
                return new FulfilledPromise(
                    $this->servicePhoneClass::toDto([
                        'id' => $parts[1],
                        'phone' => $parts[2],
                    ])
                );
            default:
                return new RejectedPromise($data);
        }
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseDeclinePhoneResult(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        switch ($data) {
            case 'ACCESS_ERROR_NUMBER_GET':
                return new FulfilledPromise('OK');
            default:
                return new RejectedPromise($data);

        }
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseReadyPhoneResult(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        switch ($data) {
            case 'ACCESS_READY':
                return new FulfilledPromise('OK');
            default:
                return new RejectedPromise($data);
        }
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseConfirmPhoneResult(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        switch ($data) {
            case 'ACCESS_ACTIVATION':
                return new FulfilledPromise('OK');
            default:
                return new RejectedPromise($data);
        }
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseStateResponse(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        $parts = explode(':', $data);
        switch ($parts[0]) {
            case 'STATUS_CANCEL': // Активация отменена
            case 'STATUS_WAIT_READY': // Ожидаение готовности
            case 'STATUS_WAIT_CODE': // Ожидание кода
            case 'STATUS_WAIT_RETRY': // Ожидание уточнения кода
            case 'STATUS_WAIT_SCREEN': // Ожидание скрина
            case 'STATUS_WAIT_RESEND': // Ожидание переотправки SMS (Активатор ждет, пока вы переотправите SMS)
                return new FulfilledPromise(
                    $this->stateResponseClass::toDto([
                        'response' => $parts[0],
                    ])
                );
            case 'STATUS_OK': // Код получен
            case 'STATUS_ACCESS': // Активация завершена
            case 'STATUS_ACCESS_SCREEN': // Активация завершена согласно скрину
                return new FulfilledPromise(
                    $this->stateResponseClass::toDto([
                        'response' => $parts[0],
                        'msg' => $parts[1]
                    ])
                );
            default:
                return new RejectedPromise($data);
        }
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    public function parseBalance(ResponseInterface $response): PromiseInterface
    {
        $data = $response->getBody()->getContents();
        $this->getLogger()->debug($data);

        $parts = explode(':', $data);
        switch ($parts[0]) {
            case 'ACCESS_BALANCE':
                return new FulfilledPromise(
                    $parts[1]
                );
            default:
                return new RejectedPromise($data);
        }
    }
}