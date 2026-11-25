<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Infra\Adapter\StorageApiAdapter\PushPermission;
use PHPUnit\Framework\TestCase;

final class PushPermissionTest extends TestCase
{
    public function testGitlabProjectAccessRazreshaetPush(): void
    {
        self::assertTrue(PushPermission::izGitlab([
            'permissions' => [
                'project_access' => ['access_level' => 30],
            ],
        ]));
    }

    public function testGitlabProjectAccessNizhePorogaZapreshchaetPush(): void
    {
        self::assertFalse(PushPermission::izGitlab([
            'permissions' => [
                'project_access' => ['access_level' => 29],
            ],
        ]));
    }

    public function testGitlabGroupAccessRazreshaetPush(): void
    {
        self::assertTrue(PushPermission::izGitlab([
            'permissions' => [
                'group_access' => ['access_level' => 40],
            ],
        ]));
    }

    public function testGitlabBezPravZapreshchaetPush(): void
    {
        self::assertFalse(PushPermission::izGitlab([
            'permissions' => [
                'project_access' => null,
                'group_access' => null,
            ],
        ]));
        self::assertFalse(PushPermission::izGitlab([]));
    }

    public function testGithubPushRazreshen(): void
    {
        self::assertTrue(PushPermission::izGithub([
            'permissions' => ['push' => true],
        ]));
    }

    public function testGithubPushZapreshchen(): void
    {
        self::assertFalse(PushPermission::izGithub([
            'permissions' => ['push' => false],
        ]));
        self::assertFalse(PushPermission::izGithub([]));
    }

    public function testGiteaPushRazreshen(): void
    {
        self::assertTrue(PushPermission::izGitea([
            'permissions' => ['push' => true],
        ]));
    }

    public function testGiteaPushZapreshchen(): void
    {
        self::assertFalse(PushPermission::izGitea([
            'permissions' => ['push' => false],
        ]));
        self::assertFalse(PushPermission::izGitea([]));
    }

    public function testGitflicPushRazreshenPoRoli(): void
    {
        self::assertTrue(PushPermission::izGitflic(['role' => 'DEVELOPER']));
        self::assertTrue(PushPermission::izGitflic(['accessLevel' => 30]));
        self::assertTrue(PushPermission::izGitflic([
            'permissions' => ['push' => true],
        ]));
    }

    public function testGitflicPushZapreshchen(): void
    {
        self::assertFalse(PushPermission::izGitflic(['role' => 'REPORTER']));
        self::assertFalse(PushPermission::izGitflic(['accessLevel' => 20]));
        self::assertFalse(PushPermission::izGitflic([]));
    }

    public function testGitversePushRazreshen(): void
    {
        self::assertTrue(PushPermission::izGitverse([
            'permissions' => ['push' => true],
        ]));
        self::assertTrue(PushPermission::izGitverse(['role' => 'MAINTAINER']));
    }

    public function testGitversePushZapreshchen(): void
    {
        self::assertFalse(PushPermission::izGitverse(['role' => 'READ']));
        self::assertFalse(PushPermission::izGitverse([]));
    }

    public function testBitbucketPushRazreshen(): void
    {
        self::assertTrue(PushPermission::izBitbucket(['permission' => 'write']));
        self::assertTrue(PushPermission::izBitbucket(['permission' => 'admin']));
        self::assertTrue(PushPermission::izBitbucket(['role' => 'MAINTAINER']));
    }

    public function testBitbucketPushZapreshchen(): void
    {
        self::assertFalse(PushPermission::izBitbucket(['permission' => 'read']));
        self::assertFalse(PushPermission::izBitbucket([]));
    }
}
