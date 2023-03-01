<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanFindDouble;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class JWTUserRepository implements UserProviderInterface, CanFindDouble
{
    const TABLE = 'jwt_user';
    public function __construct(private Connection $connection)
    {
    }

    function save(JWTUser $user): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'roles' => join(',', $user->getRoles()),
            'is_blocked' => $user->isBlocked()?1:0
        ]);
    }

    function update(JWTUser $user): void
    {
        $this->connection->update(self::TABLE, [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'roles' => join(',', $user->getRoles()),
            'is_blocked' => $user->isBlocked()?1:0
        ], ['id' => $user->getId()]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        throw new \Exception('upgradePassword');
    }

    public function refreshUser(UserInterface $user)
    {
        throw new \Exception('refreshUser');
    }

    public function supportsClass(string $class)
    {
        return JWTUser::class === $class;
    }

    /**
     * @param array|bool $row
     * @return JWTUser
     */
    private function makeUserByDbRow(array $row): JWTUser
    {
        $JWTUser = new JWTUser($row['id'], $row['email'], $row['password'], explode(',', $row['roles']), (bool)$row['is_blocked']);
        return $JWTUser;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $row = $this->findUserByEmail($identifier);

        if (empty($row)) {
            throw new \DomainException('Account not found with email='.$identifier);
        }

        $JWTUser = $this->makeUserByDbRow($row);

        if ($JWTUser->isBlocked()) {
            throw new \DomainException('Account was blocked ');
        }

        return $JWTUser;
    }

    public function isExistDoubleByEmail(string $email): bool
    {
        $row = $this->findUserByEmail($email);

        return !empty($row);
    }

    /**
     * @param string $identifier
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findUserByEmail(string $identifier): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE email = :email", ['email' => $identifier]);
        return $row;
    }

    public function getById(string $id): JWTUser
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);

        if (empty($row)) {
            throw new \Exception('User not found with id='.$id);
        }

        $user = $this->makeUserByDbRow($row);

        if ($user->isBlocked()) {
            throw new \DomainException('Account was blocked ');
        }

        return $user;
    }
}
