<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Services;

use GraphDbTest\Domain\Enums\UserStatus;
use GraphDbTest\Domain\Models\User;
use GraphDbTest\Domain\Repositories\UserRepository;

class UserService
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    public function registerUser(string $name, string $email): User
    {
        return $this->repository->createUser($name, $email, UserStatus::Active);
    }

    public function getUser(int $id): ?User
    {
        $user = $this->repository->findById($id);
        return $user instanceof User ? $user : null;
    }

    public static function createGuest(string $email): User
    {
        $repo = new UserRepository();
        return $repo->createUser('Guest', $email, UserStatus::Inactive);
    }

    public static function fromArray(array $data): User
    {
        return User::createFromArray($data);
    }
}
