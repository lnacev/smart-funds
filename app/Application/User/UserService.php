<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Investor\Investor;
use App\Domain\Investor\InvestorRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Nette\Security\Passwords;

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InvestorRepositoryInterface $investorRepository,
        private readonly Passwords $passwords,
    ) {
    }

    public function createAdmin(string $email, string $password): void
    {
        $user = new User(
            id:           null,
            email:        $email,
            passwordHash: $this->passwords->hash($password),
            role:         'admin',
            investorId:   null,
            createdAt:    new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }

    public function createInvestor(string $name, string $email, string $password): void
    {
        $investor = new Investor(
            id:        null,
            name:      $name,
            email:     $email,
            createdAt: new \DateTimeImmutable(),
        );

        $investorId = $this->investorRepository->save($investor);

        $user = new User(
            id:           null,
            email:        $email,
            passwordHash: $this->passwords->hash($password),
            role:         'investor',
            investorId:   $investorId,
            createdAt:    new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }

    public function updateInvestor(int $investorId, string $name, string $email): void
    {
        $investor = new Investor(
            id:        $investorId,
            name:      $name,
            email:     $email,
            createdAt: new \DateTimeImmutable(),
        );

        $this->investorRepository->save($investor);

        $user = $this->userRepository->findByInvestorId($investorId);
        if ($user !== null) {
            $updated = new User(
                id:           $user->id,
                email:        $email,
                passwordHash: $user->passwordHash,
                role:         $user->role,
                investorId:   $user->investorId,
                createdAt:    $user->createdAt,
            );
            $this->userRepository->save($updated);
        }
    }

    public function deleteInvestor(int $investorId): void
    {
        $this->userRepository->deleteByInvestorId($investorId);
        $this->investorRepository->delete($investorId);
    }

    public function changeInvestorPassword(int $investorId, string $newPassword): bool
    {
        $user = $this->userRepository->findByInvestorId($investorId);
        if ($user === null) {
            return false;
        }
        $this->userRepository->updatePassword($user->id, $this->passwords->hash($newPassword));
        return true;
    }

    public function assignUserToInvestor(int $investorId, string $password): void
    {
        $investor = $this->investorRepository->findById($investorId);
        if ($investor === null) {
            throw new \InvalidArgumentException('Investor neexistuje.');
        }

        $existing = $this->userRepository->findByInvestorId($investorId);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Investor již má uživatelský účet.');
        }

        $user = new User(
            id:           null,
            email:        $investor->email,
            passwordHash: $this->passwords->hash($password),
            role:         'investor',
            investorId:   $investorId,
            createdAt:    new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }

    /** @return int[] */
    public function getInvestorIdsWithAccounts(): array
    {
        return $this->userRepository->findInvestorIdsWithAccounts();
    }
}
