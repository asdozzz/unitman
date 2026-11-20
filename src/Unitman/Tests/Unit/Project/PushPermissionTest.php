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
}
