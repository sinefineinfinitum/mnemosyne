<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Repositories;

use GraphDbTest\Domain\Enums\UserStatus;
use GraphDbTest\Domain\Models\User;

class UserRepository extends BaseRepository
{
    public function createUser(string $name, string $email, UserStatus $status): User
    {
        $user = new User(
            id: count($this->storage) + 1,
            name: $name,
            email: $email,
            status: $status,
        );

        $this->storage[$user->getId()] = $user;

        return $user;
    }

    public function save(object $entity): void
    {
        if ($entity instanceof User) {
            $this->storage[$entity->getId()] = $entity;
        }
    }

    public function findActiveUsers(): array
    {
        $result = [];
        foreach ($this->storage as $user) {
            if ($user instanceof User && $user->getStatus() === UserStatus::Active) {
                $result[] = $user;
            }
        }
        return $result;
    }
}
