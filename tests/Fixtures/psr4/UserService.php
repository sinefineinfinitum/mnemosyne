<?php declare(strict_types=1);

namespace App\Service;

use App\Abstracts\BaseService;
use App\Contracts\ServiceInterface;
use App\Models\User;

class UserService extends BaseService implements ServiceInterface
{
    public function findById(int $id, ?bool $active = true): ?User
    {
        return null;
    }

    public static function create(string $name, array $data = []): User
    {
        return new User();
    }
}
