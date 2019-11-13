<?php
namespace unapi\sms\smsarea\dto;

use unapi\interfaces\DtoInterface;
use unapi\sms\common\dto\TaskInterface;

/**
 * Class StateResponseDto
 */
class StateResponseDto implements StateResponseInterface
{
    /** @var string */
    private $response;
    /** @var string */
    private $msg;

    /**
     * @param string $response
     * @param string $msg
     */
    public function __construct(string $response, string $msg = '')
    {
        $this->response = $response;
        $this->msg = $msg;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * @param array $data
     * @return TaskInterface
     */
    public static function toDto(array $data): DtoInterface
    {
        return new self($data['response'], (string)$data['msg']);
    }
}