<?php

namespace App\Unitman\Business\Model\Repo;

enum RepoType: string
{
    case GITLAB = 'GITLAB';
    case GITHUB = 'GITHUB';
    case BITBUCKET = 'BITBUCKET';
    case GITEA = 'GITEA';
    case FORGEJO = 'FORGEJO';
    case CODEBERG = 'CODEBERG';
    case GITFLIC = 'GITFLIC';
    case GITVERSE = 'GITVERSE';
}
