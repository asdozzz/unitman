<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Business\Model\Account;
use App\Account\Business\Port\CanGenerateEmailVerificationCode;
use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\CanGenerateTemporaryPassword;
use App\Account\Business\Port\CanHashVerificationCode;
use App\Account\Business\Port\CanIssueAccessToken;
use App\Account\Business\Port\CanSendEmailVerificationCode;
use App\Account\Business\Port\CanSignOAuthState;
use App\Account\Business\Port\EmailVerificationCodeRepository;
use App\Account\Business\Port\OAuthExchangeCodeRepository;
use App\Account\Business\Port\OAuthProvayderRegistry as OAuthProvayderRegistryPort;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\RefreshTokenRepository;
use App\Account\Business\Port\SocialIdentityRepository;
use App\Account\Business\Port\UmeetOpoveshatOZaproseAktivacii;
use App\Account\Business\Port\UmeetOtpravlyatVremenniyParol;
use App\Account\Business\Port\ZaprositModeliProvaydera;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Business\Utils\EventTypeMapFactory;
use App\Account\Infra\Adapter\AccountActivationRequestNotifier;
use App\Account\Infra\Adapter\HttpZaprositModeliProvaydera;
use App\Account\Infra\Adapter\PasswordEmailVerificationCodeHasher;
use App\Account\Infra\Adapter\RandomEmailVerificationCodeGenerator;
use App\Account\Infra\Adapter\SymfonyMailerEmailVerificationCodeSender;
use App\Account\Infra\OAuth\GithubOAuthProvayder;
use App\Account\Infra\OAuth\GoogleOAuthProvayder;
use App\Account\Infra\OAuth\HmacOAuthStateCodec;
use App\Account\Infra\OAuth\OAuthProvayderRegistry;
use App\Account\Infra\Adapter\LexikAccessTokenIssuer;
use App\Account\Infra\Adapter\OpenSslShifr;
use App\Account\Infra\Adapter\RamseyUuidGenerator;
use App\Account\Infra\Adapter\RandomRefreshTokenGenerator;
use App\Account\Infra\Adapter\RandomTemporaryPasswordGenerator;
use App\Account\Infra\Adapter\SymfonyMailerVremenniyParolOtpravitel;
use App\Account\Infra\Repository\SqlAccountRepository;
use App\Account\Infra\Repository\SqlEmailVerificationCodeRepository;
use App\Account\Infra\Repository\SqlOAuthExchangeCodeRepository;
use App\Account\Infra\Repository\SqlOpenCodeCredentialRepository;
use App\Account\Infra\Repository\SqlRefreshTokenRepository;
use App\Account\Infra\Repository\SqlSocialIdentityRepository;
use App\App\Infra\EventStore\AuthorMessageDecorator;
use App\Utils\EventSauce\ProjectionsManager;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;
use EventSauce\EventSourcing\MessageDecoratorChain;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\PayloadSerializerSupportingObjectMapperAndSerializablePayload;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineMessageRepository;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$appEnv','%env(APP_ENV)%')
        ->bind('$defaultLocale','%env(ACCOUNT_DEFAULT_LOCALE)%')
        ->bind('$refreshTokenTtl','%env(int:REFRESH_TOKEN_TTL)%')
        ->bind('$openCodeCredentialsKey','%env(OPENCODE_CREDENTIALS_KEY)%')
        ->bind('$mailerFromAddress','%env(MAILER_FROM_ADDRESS)%')
        ->bind('$mailerFromName','%env(MAILER_FROM_NAME)%')
        ->bind('$emailVerificationTtl','%env(int:EMAIL_VERIFICATION_TTL)%')
        ->bind('$emailVerificationMaxAttempts','%env(int:EMAIL_VERIFICATION_MAX_ATTEMPTS)%')
        ->bind('$emailVerificationResendCooldown','%env(int:EMAIL_VERIFICATION_RESEND_COOLDOWN)%')
        ->bind('$oauthGoogleClientId','%env(OAUTH_GOOGLE_CLIENT_ID)%')
        ->bind('$oauthGoogleClientSecret','%env(OAUTH_GOOGLE_CLIENT_SECRET)%')
        ->bind('$oauthGithubClientId','%env(OAUTH_GITHUB_CLIENT_ID)%')
        ->bind('$oauthGithubClientSecret','%env(OAUTH_GITHUB_CLIENT_SECRET)%')
        ->bind('$oauthRedirectBaseUrl','%env(OAUTH_REDIRECT_BASE_URL)%')
        ->bind('$oauthFrontendUrl','%env(OAUTH_FRONTEND_URL)%')
        ->bind('$oauthStateTtl','%env(int:OAUTH_STATE_TTL)%')
        ->bind('$oauthExchangeCodeTtl','%env(int:OAUTH_EXCHANGE_CODE_TTL)%');

    $services->load('App\\Account\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
            // Сервисы ниже собираются explicit-определениями: у них
            // неавтовайримые аргументы (секрет state, список провайдеров).
            './Infra/OAuth/HmacOAuthStateCodec.php',
            './Infra/OAuth/OAuthProvayderRegistry.php',
        ])
        ->public();

    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);
    $services->set(RefreshTokenRepository::class, SqlRefreshTokenRepository::class);
    $services->set(CanGenerateRefreshToken::class, RandomRefreshTokenGenerator::class);
    $services->set(CanIssueAccessToken::class, LexikAccessTokenIssuer::class);
    $services->set(UmeetShifrovatSekrety::class, OpenSslShifr::class);
    $services->set(OpenCodeCredentialRepository::class, SqlOpenCodeCredentialRepository::class);
    $services->set(ZaprositModeliProvaydera::class, HttpZaprositModeliProvaydera::class);
    $services->set(CanGenerateTemporaryPassword::class, RandomTemporaryPasswordGenerator::class);
    $services->set(UmeetOtpravlyatVremenniyParol::class, SymfonyMailerVremenniyParolOtpravitel::class);
    $services->set(UmeetOpoveshatOZaproseAktivacii::class, AccountActivationRequestNotifier::class);
    $services->set(EmailVerificationCodeRepository::class, SqlEmailVerificationCodeRepository::class);
    $services->set(CanGenerateEmailVerificationCode::class, RandomEmailVerificationCodeGenerator::class);
    $services->set(CanHashVerificationCode::class, PasswordEmailVerificationCodeHasher::class);
    $services->set(CanSendEmailVerificationCode::class, SymfonyMailerEmailVerificationCodeSender::class);
    $services->set(SocialIdentityRepository::class, SqlSocialIdentityRepository::class);
    $services->set(OAuthExchangeCodeRepository::class, SqlOAuthExchangeCodeRepository::class);
    $services->set(CanSignOAuthState::class, HmacOAuthStateCodec::class)
        ->args(['%env(OAUTH_STATE_SECRET)%']);
    $services->set(OAuthProvayderRegistryPort::class, OAuthProvayderRegistry::class)
        ->args([[
            service(GoogleOAuthProvayder::class),
            service(GithubOAuthProvayder::class),
        ]]);

    $services->set('account.event_type_mapper', ExplicitlyMappedClassNameInflector::class)
        ->factory([service(EventTypeMapFactory::class), 'getMap']);

    $services->set('account.event_serializer', ConstructingMessageSerializer::class)
        ->args([
            service('account.event_type_mapper'),
            service(PayloadSerializerSupportingObjectMapperAndSerializablePayload::class)
        ]);

    $services->set('account.message_repository', DoctrineMessageRepository::class)
        ->args([
            service(Connection::class),
            'account_event_store',
            service('account.event_serializer'),
            0,
            service(DefaultTableSchema::class),
            service(StringIdEncoder::class)
        ]);

    $services->set('account.default_decorator', DefaultHeadersDecorator::class)
        ->args([service('account.event_type_mapper')]);

    $services->set('account.message_decorator', MessageDecoratorChain::class)
        ->args([service('account.default_decorator'), service(AuthorMessageDecorator::class)]);

    $services->set('account.stream_repository', DoctrineStreamRepository::class)
        ->args([
            service(Connection::class),
            'account_event_store',
            service('account.event_serializer'),
            service('account.event_type_mapper'),
            service('account.message_decorator'),
            service('account.message_repository'),
        ]);

    $services->set('account.projections_manager', ProjectionsManager::class)
        ->arg('$projections', tagged_iterator('account.sync_projection'))
        ->arg('$checkpointStore', service('app.checkpoint_store'))
        ->arg('$eventsRepository', service('account.stream_repository'));

    $services->set('account.event_store', EventSourcedAggregateRootRepository::class)
        ->args([
            Account::class,
            service('account.message_repository'),
            null,
            service('account.message_decorator'),
            service('account.event_type_mapper'),
        ]);

    $services->set(SqlAccountRepository::class)
        ->args([
            service('account.projections_manager')
        ]);
};
