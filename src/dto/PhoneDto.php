<?php
namespace unapi\sms\smsarea\dto;

use unapi\interfaces\DtoInterface;
use unapi\sms\common\interfaces\PhoneInterface;

/**
 * Class PhoneDto
 */
class PhoneDto implements PhoneInterface
{
    /** @var string */
    private $number;

    /**
     * @param string $number
     */
    public function __construct(string $number)
    {
        $this->number = preg_replace('/[^0-9]/', '', $number);
        $this->number = substr($this->number, -10, 10);
        if (strlen($this->number) !== 10)
            throw new \InvalidArgumentException('Incorrect Phone number');
    }
    /**
     * @param string $prepend
     * @return string
     */
    public function getNumber(string $prepend = ''): string
    {
        return $prepend . $this->number;
    }

    /**
     * @param array $data
     * @return PhoneInterface
     */
    public static function toDto(array $data): DtoInterface
    {
        return new self($data['phone']);
    }
}