<?php declare(strict_types=1);

namespace App;

use App\Repository\UserRepository;
use App\Service\UserService;

function bootstrap(UserService $service, UserRepository $repository): void
{
    $service->getUser($repository, 1);
}
