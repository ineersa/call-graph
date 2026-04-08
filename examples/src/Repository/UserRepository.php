<?php declare(strict_types=1);

namespace App\Repository;

final class UserRepository
{
    /**
     * @return array{id: int}
     */
    public function find(int $id): array
    {
        return ['id' => $id];
    }
}
