<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Nette\Database\Explorer;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->database
            ->table('users')
            ->where('email', $email)
            ->fetch();

        return $row !== null ? $this->rowToUser($row) : null;
    }

    public function findById(int $id): ?User
    {
        $row = $this->database
            ->table('users')
            ->get($id);

        return $row !== null ? $this->rowToUser($row) : null;
    }

    public function findByInvestorId(int $investorId): ?User
    {
        $row = $this->database
            ->table('users')
            ->where('investor_id', $investorId)
            ->fetch();

        return $row !== null ? $this->rowToUser($row) : null;
    }

    public function save(User $user): int
    {
        if ($user->id === null) {
            $row = $this->database->table('users')->insert([
                'email'         => $user->email,
                'password_hash' => $user->passwordHash,
                'role'          => $user->role,
                'investor_id'   => $user->investorId,
                'created_at'    => $user->createdAt->format('Y-m-d H:i:s'),
            ]);

            return (int) $row->id;
        }

        $this->database->table('users')->get($user->id)?->update([
            'email'         => $user->email,
            'password_hash' => $user->passwordHash,
            'role'          => $user->role,
            'investor_id'   => $user->investorId,
        ]);

        return $user->id;
    }

    public function deleteByInvestorId(int $investorId): void
    {
        $this->database->table('users')->where('investor_id', $investorId)->delete();
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $this->database->table('users')
            ->where('id', $userId)
            ->update(['password_hash' => $hash]);
    }

    public function findInvestorIdsWithAccounts(): array
    {
        return $this->database
            ->table('users')
            ->where('investor_id IS NOT NULL')
            ->fetchPairs(null, 'investor_id');
    }

    private function rowToUser(\Nette\Database\Table\ActiveRow $row): User
    {
        return new User(
            id:           $row->id,
            email:        $row->email,
            passwordHash: $row->password_hash,
            role:         $row->role,
            investorId:   $row->investor_id,
            createdAt:    \DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
