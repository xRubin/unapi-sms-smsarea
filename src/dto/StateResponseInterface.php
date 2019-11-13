<?php
namespace unapi\sms\smsarea\dto;

use unapi\interfaces\DtoInterface;

interface StateResponseInterface extends DtoInterface
{
    /**
     * @return string
     */
    public function getResponse(): string;

    /**
     * @return string
     */
    public function getMsg(): string;
}
