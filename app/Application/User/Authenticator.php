<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\UserRepositoryInterface;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator as NetteAuthenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

final class Authenticator implements NetteAuthenticator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Passwords $passwords,
    ) {
    }

    public function authenticate(string $user, string $password): SimpleIdentity
    {
        $record = $this->userRepository->findByEmail($user);

        if ($record === null || !$this->passwords->verify($password, $record->passwordHash)) {
            throw new AuthenticationException('Nesprávný e-mail nebo heslo.');
        }

        return new SimpleIdentity($record->id, $record->role, [
            'email'      => $record->email,
            'investorId' => $record->investorId,
        ]);
    }
}
