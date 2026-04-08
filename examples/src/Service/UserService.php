<?php declare(strict_types=1);

namespace App\Service;

use App\Audit\Logger;
use App\Repository\UserRepository;

final class UserService
{
    /**
     * @return array{id: int}
     */
    public function getUser(UserRepository $repository, int $id): array
    {
        Logger::log('Loading user');

        return $repository->find($id);
    }
}
