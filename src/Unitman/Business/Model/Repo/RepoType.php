<?php

namespace App\Unitman\Business\Model\Repo;

enum RepoType: string
{
    case GITLAB = 'GITLAB';
    case GITHUB = 'GITHUB';
    case BITBUCKET = 'BITBUCKET';
}
