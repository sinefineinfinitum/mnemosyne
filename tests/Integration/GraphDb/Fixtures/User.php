<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Models;

use GraphDbTest\Domain\Contracts\LoggableInterface;
use GraphDbTest\Domain\Enums\UserStatus;
use GraphDbTest\Domain\Support\TimestampTrait;

class User implements LoggableInterface
{
    use TimestampTrait;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private UserStatus $status,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function getLogContext(): array
    {
        return [
            'user_id' => $this->id,
            'email' => $this->email,
        ];
    }

    public static function createFromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            status: $data['status'] ?? UserStatus::Active,
        );
    }
}
