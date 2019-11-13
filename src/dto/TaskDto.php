<?php
namespace unapi\sms\smsarea\dto;

use unapi\interfaces\DtoInterface;
use unapi\sms\common\dto\TaskInterface;

/**
 * Class TaskDto
 */
class TaskDto implements TaskInterface
{
    /** @var string */
    private $id;

    /**
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param array $data
     * @return TaskInterface
     */
    public static function toDto(array $data): DtoInterface
    {
        return new self($data['id']);
    }
}