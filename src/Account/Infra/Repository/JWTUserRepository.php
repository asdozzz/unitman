<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\UmeetPoluchatAccountDlySystemi;
use App\Account\Business\Port\UmeetPoluchatNastroikiAccounta;
use App\Account\Business\Port\UmeetPoluchatSpisokVsehPolzovatelei;
use App\Account\Business\ReadModel\AccountForManaging;
use App\Account\Business\ReadModel\AccountSettingsReadModel;
use App\Account\Business\ReadModel\UserList;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<JWTUser>
 * */
final class JWTUserRepository implements UserProviderInterface, CanFindDouble, UmeetPoluchatSpisokVsehPolzovatelei, UmeetPoluchatAccountDlySystemi, UmeetPoluchatNastroikiAccounta
{
    const TABLE = 'jwt_user';
    public function __construct(private Connection $connection)
    {
    }

    function truncate(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("TRUNCATE $table");
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id       varchar(128) not null
                    constraint jwt_user_pk
                        primary key,
                email    varchar      not null
                    constraint jwt_user_email
                        unique
                            deferrable,
                password varchar      not null,
                locale varchar,
                roles    varchar,
                is_blocked bit,
                nickname varchar
            );
        ");
    }

    function destroy(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DROP TABLE IF EXISTS $table");
    }

    function save(JWTUser $user): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'roles' => join(',', $user->getRoles()),
            'is_blocked' => $user->isBlocked()?1:0,
            'locale' => $user->getLocale(),
            'nickname' => $user->getNickname()
        ]);
    }

    function update(JWTUser $user): void
    {
        $this->connection->update(self::TABLE, [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'roles' => join(',', $user->getRoles()),
            'is_blocked' => $user->isBlocked()?1:0,
            'locale' => $user->getLocale(),
            'nickname' => $user->getNickname()
        ], ['id' => $user->getId()]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        throw new \Exception('upgradePassword');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new \Exception('refreshUser');
    }

    public function supportsClass(string $class): bool
    {
        return JWTUser::class === $class;
    }

    /**
     * @param array $row
     * @return JWTUser
     */
    private function makeUserByDbRow(array $row): JWTUser
    {
        $JWTUser = new JWTUser($row['id'], $row['email'], $row['password'], explode(',', $row['roles']), (bool)$row['is_blocked'], $row['locale'], $row['nickname']);
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

    public function isExistDoubleByNickname(string $id, string $nickname): bool
    {
        $row = $this->findUserByNickname($nickname, $id);

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

    public function findUserByNickname(string $nickname, string $id): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE nickname = :nickname and id != :id", ['nickname' => $nickname, 'id' => $id]);
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
        return new UserList($row['id'], $row['email'], $row['is_blocked'], $row['nickname']);
    }

    private function makeAccountForManaging(array $row): AccountForManaging
    {
        return new AccountForManaging($row['id'], $row['email'], $row['is_blocked'], $row['roles'], $row['password'], $row['nickname']);
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

    function findSystemAccountId(): ?string
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT id FROM $table WHERE roles = :role", ['role' => Role::ROLE_SYSTEM->value]);

        if (empty($row)) {
            return null;
        }

        return $row['id'];
    }

    function getSystemAccount(): JWTUser
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE roles = :role", ['role' => Role::ROLE_SYSTEM->value]);

        if (empty($row)) {
            throw new \DomainException('account.system_account_not_found');
        }

        return $this->makeUserByDbRow($row);
    }

    function poluchitNastroikiAccounta(string $accountId): AccountSettingsReadModel
    {
        $row = $this->findRowById($accountId);

        if (empty($row)) {
            throw new \DomainException('account.settings_not_found');
        }

        return new AccountSettingsReadModel($row['nickname']);
    }

    function resetById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DELETE FROM $table where id = '$id'");
    }
}
