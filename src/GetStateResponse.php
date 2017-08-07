<?php
namespace unapi\services\smsarea;

class GetStateResponse
{
    /** @var string  */
    private $response;
    /** @var string  */
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
}