<?php
namespace unapi\sms\smsarea\dto;

use unapi\interfaces\DtoInterface;
use unapi\sms\common\dto\ServicePhoneInterface;
use unapi\sms\common\dto\TaskInterface;
use unapi\sms\common\interfaces\PhoneInterface;

/**
 * Class ServicePhoneDto
 */
class ServicePhoneDto implements ServicePhoneInterface
{
    /** @var TaskInterface */
    private $id;
    /** @var PhoneInterface */
    private $phone;

    /**
     * @param TaskInterface $id
     * @param PhoneInterface $phone
     */
    public function __construct(TaskInterface $id, PhoneInterface $phone)
    {
        $this->id = $id;
        $this->phone = $phone;
    }

    /**
     * @return TaskInterface
     */
    public function getId(): TaskInterface
    {
        return $this->id;
    }

    /**
     * @return PhoneInterface
     */
    public function getPhone(): PhoneInterface
    {
        return $this->phone;
    }

    /**
     * @param array $data
     * @return ServicePhoneInterface
     */
    public static function toDto(array $data): DtoInterface
    {
        return new self(
            new TaskDto($data['id']),
            new PhoneDto($data['phone'])
        );
    }
}