<?php

namespace unapi\sms\smsarea;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use unapi\sms\common\dto\ServicePhoneInterface;
use unapi\sms\common\LeaseServiceInterface;
use unapi\sms\smsarea\dto\StateResponseInterface;

class SmsareaService implements LeaseServiceInterface, LoggerAwareInterface
{
    /** @var SmsareaClient */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var ParserInterface */
    private $parser;

    /** @var string */
    private $key;
    /** @var string */
    private $country = 'ru';
    /** @var string */
    private $service = 'or';
    /** @var int */
    private $retryCount = 100;

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

        if (!isset($config['parser'])) {
            $this->parser = new Parser(['logger' => $this->logger]);
        } else {
            if ($config['parser'] instanceof ParserInterface) {
                $this->parser = $config['parser'];
            } else {
                throw new \InvalidArgumentException('Parser must be instance of ParserInterface');
            }
        }

        if (isset($config['retryCount'])) {
            $this->retryCount = $config['retryCount'];
        }

        if (isset($config['country'])) {
            $this->country = $config['country'];
        }

        if (isset($config['service'])) {
            $this->service = $config['service'];
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
     * Cоздает операцию на использование виртуального номера
     * @return PromiseInterface
     */
    public function getPhone(): PromiseInterface
    {
        $this->getLogger()->info('Запрос на новый номер');

        return $this->client->requestAsync('GET', '/stubs/handler_api.php', [
            'query' => [
                'api_key' => $this->key,
                'action' => 'getNumber',
                'country' => $this->country,
                'service' => $this->service,
                'count' => 1
            ]
        ])->then(function (ResponseInterface $response) {
            return $this->parser->parseServicePhone($response);
        });
    }

    /**
     * @param ServicePhoneInterface $servicePhone
     * @return PromiseInterface
     */
    public function declinePhone(ServicePhoneInterface $servicePhone): PromiseInterface
    {
        $this->getLogger()->info('Отклоняем задачу {taskId}' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 10)
            ->then(function (ResponseInterface $response) {
                return $this->parser->parseDeclinePhoneResult($response);
            });
    }

    /**
     * @param ServicePhoneInterface $servicePhone
     * @return PromiseInterface
     */
    public function readyPhone(ServicePhoneInterface $servicePhone): PromiseInterface
    {
        $this->getLogger()->info('Активируем задачу {taskId}' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 1)
            ->then(function (ResponseInterface $response) {
                return $this->parser->parseReadyPhoneResult($response);
            });
    }

    /**
     * @param ServicePhoneInterface $servicePhone
     * @return PromiseInterface
     */
    public function confirmPhone(ServicePhoneInterface $servicePhone): PromiseInterface
    {
        $this->getLogger()->info('Подтверждаем что задача {taskId} выполнена' , ['taskId' => $servicePhone->getId()]);

        return $this->setStatus($servicePhone->getId(), 6)
            ->then(function (ResponseInterface $response) {
                return $this->parser->parseConfirmPhoneResult($response);
            });
    }

    /**
     * @param string $id
     * @param int $status
     * @return PromiseInterface
     */
    protected function setStatus(string $id, int $status): PromiseInterface
    {
        $this->getLogger()->info('Выставляем задаче {taskId} статус {status}' , ['taskId' => $id, 'status' => $status]);

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
     * @param ServicePhoneInterface $servicePhone
     * @return PromiseInterface
     */
    public function getSmsMessage(ServicePhoneInterface $servicePhone): PromiseInterface
    {
        return $this->waitState($servicePhone->getId(), ['STATUS_OK'], 0)
            ->then(function (StateResponseInterface $state) {
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

        $this->getLogger()->debug('Задача {taskId} ожидает статус {status}' , ['taskId' => $id, 'status' => var_export($waitedResponses, true)]);

        return $this->getState($id)->then(function (StateResponseInterface $state) use ($id, $waitedResponses, $cnt) {

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
            return $this->parser->parseStateResponse($response);
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
            return $this->parser->parseBalance($response);
        });
    }
}
