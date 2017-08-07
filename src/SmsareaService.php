<?php

namespace unapi\sms\smsarea;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use unapi\sms\common\PhoneDto;
use unapi\sms\common\ServicePhoneDto;
use unapi\sms\common\SmsServiceInterface;
use unapi\sms\common\TaskDto;

class SmsareaService implements SmsServiceInterface, LoggerAwareInterface
{
    /** @var SmsareaClient */
    private $client;
    /** @var string */
    private $key;
    /** @var LoggerInterface */
    private $logger;
    /** @var int */
    private $retryCount = 100;

    /** @var string */
    private $country = 'ru';
    /** @var string */
    private $service = 'or';

    /**
     * @param array $config Service configuration settings.
     */
    public function __construct(array $config = [])
    {
        if (isset($config['key'])) {
            $this->key = $config['key'];
        } else {
            throw new \InvalidArgumentException('Antigate api key required');
        }

        if (!isset($config['client'])) {
            $this->client = new SmsareaClient();
        } elseif ($config['client'] instanceof SmsareaClient) {
            $this->client = $config['client'];
        } else {
            throw new \InvalidArgumentException('Client must be instance of SmsareaClient');
        }

        if (!isset($config['logger'])) {
            $this->logger = new NullLogger();
        } elseif ($config['logger'] instanceof LoggerInterface) {
            $this->setLogger($config['logger']);
        } else {
            throw new \InvalidArgumentException('Logger must be instance of LoggerInterface');
        }

        if (isset($config['retryCount'])) {
            $this->retryCount = $config['retryCount'];
        }

        if (isset($config['country'])) {
            $this->setCountry($config['country']);
        }

        if (isset($config['service'])) {
            $this->setService($config['service']);
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
     * @param string $value
     * @return $this
     */
    public function setCountry(string $value)
    {
        $this->country = $value;
        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setService(string $value)
    {
        $this->service = $value;
        return $this;
    }

    /**
     * Cоздает операцию на использование виртуального номера
     * @return PromiseInterface
     */
    public function getPhone(): PromiseInterface
    {
        $this->logger->debug('Запрос на новый номер');

        return $this->client->requestAsync('GET', '/stubs/handler_api.php', [
            'query' => [
                'api_key' => $this->key,
                'action' => 'getNumber',
                'country' => $this->country,
                'service' => $this->service,
                'count' => 1
            ]
        ])->then(function (ResponseInterface $response) {
            $data = $response->getBody()->getContents();
            $this->logger->debug($data);
            $parts = explode(':', $data);
            switch ($parts[0]) {
                case 'ACCESS_NUMBER':
                    return new FulfilledPromise(
                        new ServicePhoneDto(
                            new TaskDto($parts[1]),
                            new PhoneDto($parts[2])
                        )
                    );
                default:
                    return new RejectedPromise($data);
            }
        });
    }

    /**
     * @param ServicePhoneDto $servicePhone
     * @return PromiseInterface
     */
    public function declinePhone(ServicePhoneDto $servicePhone): PromiseInterface
    {
        $this->logger->debug('Отклоняем задачу {taskId}' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 10)
            ->then(function (ResponseInterface $response) {
                $data = $response->getBody()->getContents();
                $this->logger->debug($data);
                switch ($data) {
                    case 'ACCESS_ERROR_NUMBER_GET':
                        return new FulfilledPromise('OK');
                    default:
                        return new RejectedPromise($data);
                }
            });
    }

    /**
     * @param ServicePhoneDto $servicePhone
     * @return PromiseInterface
     */
    public function readyPhone(ServicePhoneDto $servicePhone): PromiseInterface
    {
        $this->logger->debug('Активируем задачу {taskId}' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 1)
            ->then(function (ResponseInterface $response) {
                $data = $response->getBody()->getContents();
                $this->logger->debug($data);
                switch ($data) {
                    case 'ACCESS_READY':
                        return new FulfilledPromise('OK');
                    default:
                        return new RejectedPromise($data);
                }
            });
    }

    /**
     * @param ServicePhoneDto $servicePhone
     * @return PromiseInterface
     */
    public function confirmPhone(ServicePhoneDto $servicePhone): PromiseInterface
    {
        $this->logger->debug('Подтверждаем что задача {taskId} выполнена' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 6)
            ->then(function (ResponseInterface $response) {
                $data = $response->getBody()->getContents();
                $this->logger->debug($data);
                switch ($data) {
                    case 'ACCESS_ACTIVATION':
                        return new FulfilledPromise('OK');
                    default:
                        return new RejectedPromise($data);
                }
            });
    }

    /**
     * @param string $id
     * @param int $status
     * @return PromiseInterface
     */
    protected function setStatus(string $id, int $status): PromiseInterface
    {
        $this->logger->debug('Выставляем задаче {taskId} статус {status}' , ['taskId' => $id, 'status' => $status]);

        return $this->client->requestAsync('GET', '/stubs/handler_api.php', [
            'query' => [
                'api_key' => $this->key,
                'action' => 'setStatus',
                'id' => $id,
                'status' => $status
            ]
        ]);
    }

    /**
     * @param ServicePhoneDto $servicePhone
     * @return PromiseInterface
     */
    public function getSmsMessage(ServicePhoneDto $servicePhone): PromiseInterface
    {
        return $this->waitState($servicePhone->getId(), ['STATUS_OK'], 0)
            ->then(function (GetStateResponse $state) {
                return new FulfilledPromise(
                    $state->getMsg()
                );
            });
    }

    /**
     * @param string $id
     * @param array $waitedResponses
     * @param int $cnt
     * @return PromiseInterface
     */
    protected function waitState(string $id, array $waitedResponses, int $cnt): PromiseInterface
    {
        if ($cnt > $this->retryCount)
            return new RejectedPromise('Terminated by waitState counter');

        $this->logger->debug('Задача {taskId} ожидает статус {status}' , ['taskId' => $id, 'status' => var_export($waitedResponses, true)]);

        return $this->getState($id)->then(function (GetStateResponse $state) use ($id, $waitedResponses, $cnt) {

            if (!in_array($state->getResponse(), $waitedResponses))
                return $this->waitState($id, $waitedResponses, ++$cnt);

            return $state;
        });
    }

    /**
     * Позволяет получить информацию о состоянии операции
     * @param string $id
     * @return PromiseInterface
     */
    public function getState(string $id): PromiseInterface
    {
        return $this->client->requestAsync('GET', '/stubs/handler_api.php', [
            'query' => [
                'api_key' => $this->key,
                'action' => 'getStatus',
                'id' => $id,
            ]
        ])->then(function (ResponseInterface $response) use ($id) {
            $data = $response->getBody()->getContents();
            $this->logger->debug($data);
            $parts = explode(':', $data);
            switch ($parts[0]) {
                case 'STATUS_CANCEL': // Активация отменена
                case 'STATUS_WAIT_READY': // Ожидаение готовности
                case 'STATUS_WAIT_CODE': // Ожидание кода
                case 'STATUS_WAIT_RETRY': // Ожидание уточнения кода
                case 'STATUS_WAIT_SCREEN': // Ожидание скрина
                case 'STATUS_WAIT_RESEND': // Ожидание переотправки SMS (Активатор ждет, пока вы переотправите SMS)
                    return new FulfilledPromise(
                        new GetStateResponse($parts[0])
                    );
                case 'STATUS_OK': // Код получен
                case 'STATUS_ACCESS': // Активация завершена
                case 'STATUS_ACCESS_SCREEN': // Активация завершена согласно скрину
                    return new FulfilledPromise(
                        new GetStateResponse($parts[0], $parts[1])
                    );
                default:
                    return new RejectedPromise($data);
            }
        });
    }

    /**
     * Получение баланса
     * @return PromiseInterface
     */
    public function getBalance(): PromiseInterface
    {
        return $this->client->requestAsync('GET', '/stubs/handler_api.php', [
            'query' => [
                'api_key' => $this->key,
                'action' => 'getBalance',
            ]
        ])->then(function (ResponseInterface $response) {
            $data = $response->getBody()->getContents();
            $parts = explode(':', $data);
            switch ($parts[0]) {
                case 'ACCESS_BALANCE':
                    return new FulfilledPromise(
                        $parts[1]
                    );
                default:
                    return new RejectedPromise($data);
            }
        });
    }
}
