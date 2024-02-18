<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\UmeetPoluchatSpisokVsehPolzovatelei;
use App\Account\Business\ReadModel\AccountForManaging;
use App\Account\Business\ReadModel\UserList;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class JWTUserRepository implements UserProviderInterface, CanFindDouble, UmeetPoluchatSpisokVsehPolzovatelei
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
     * @param array $row
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
            throw new \DomainException('#1. Account was blocked ');
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

    public function getActiveById(string $id): JWTUser
    {
        $user = $this->getById($id);

        if ($user->isBlocked()) {
            throw new \DomainException('#2. Account was blocked ');
        }

        return $user;
    }

    /**
     * @param string $id
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findRowById(string $id): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        return $row;
    }

    /**
     * @param string $id
     * @return JWTUser
     * @throws \Doctrine\DBAL\Exception
     */
    public function getById(string $id): JWTUser
    {
        $row = $this->findRowById($id);

        if (empty($row)) {
            throw new \Exception('User not found with id=' . $id);
        }

        $user = $this->makeUserByDbRow($row);
        return $user;
    }

    private function makeUserListModel(array $row): UserList
    {
        return new UserList($row['id'], $row['email'], $row['is_blocked']);
    }

    private function makeAccountForManaging(array $row): AccountForManaging
    {
        return new AccountForManaging($row['id'], $row['email'], $row['is_blocked'], $row['roles'], $row['password']);
    }

    function poluchitSpisokVsehPolzovatelei(): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUserListModel($row);
        }

        return $result;
    }

    function poluchitSpisokVsehPolzovateleiDlyAdministrirovaniya(): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeAccountForManaging($row);
        }

        return $result;
    }
}
