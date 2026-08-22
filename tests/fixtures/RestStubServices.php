<?php
/**
 * Stub RestService implementations for characterization tests.
 */

declare(strict_types=1);

namespace Core\Tests\Fixtures;

use Core\Base\HttpMethod;
use Core\Base\RestService;
use Core\Base\SecurityLevel;

/**
 * Valid baseline declarations used by most stubs.
 */
abstract class RestStubBase extends RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    protected ?HttpMethod $httpMethod = HttpMethod::Post;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => false,
        'public'          => true,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'read',
        'visibilityAware' => false,
    ];

    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'  => false,
        'audit' => false,
    ];

    /** @var array<int, array<string, mixed>> */
    protected $paramSpecs = [];

    /**
     * @return array<string, mixed>
     */
    protected function process()
    {
        return [
            'data'   => ['ok' => true],
            'status' => 'SUCCESS',
        ];
    }
}

class UndefinedSecurityStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Undefined;
}

class MissingHttpMethodStub extends RestStubBase
{
    protected ?HttpMethod $httpMethod = null;
}

class InconsistentPublicAuthStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => true,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'read',
        'visibilityAware' => false,
    ];
}

class InconsistentAuthenticatedNoAuthStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Authenticated;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => false,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'read',
        'visibilityAware' => false,
    ];
}

class MissingSecurityMetadataStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $security = [
        'auth'   => false,
        'public' => true,
    ];
}

class UnknownPolicyKeyStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'      => false,
        'audit'     => false,
        'typoGuard' => true,
    ];
}

class MethodMatchStub extends RestStubBase
{
    protected ?HttpMethod $httpMethod = HttpMethod::Post;
}

class MethodMismatchStub extends RestStubBase
{
    protected ?HttpMethod $httpMethod = HttpMethod::Post;
}

class UnauthenticatedStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Authenticated;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'read',
        'visibilityAware' => false,
    ];
}

class OwnerDefaultDenyStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Owner;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => 'id',
        'operation'       => 'read',
        'visibilityAware' => false,
    ];
}

class AdminMissingRoleStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Admin;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'admin',
        'visibilityAware' => false,
    ];
}

class CsrfDisabledStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'  => false,
        'audit' => false,
    ];
}

class CsrfValidStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'  => true,
        'audit' => false,
    ];
}

class CsrfFailedStub extends CsrfValidStub
{
}

/**
 * Legacy/current behaviour: when no csrf_token is stored in session the
 * CSRF gate is skipped. This may be tightened in a future refactor.
 */
class CsrfLegacySkipStub extends CsrfValidStub
{
}

class RateLimitDisabledStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'      => false,
        'audit'     => false,
        'rateLimit' => false,
    ];
}

class RateLimitNullStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'      => false,
        'audit'     => false,
        'rateLimit' => null,
    ];
}

class RateLimitBucketStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'      => false,
        'audit'     => false,
        'rateLimit' => 'auth',
    ];
}

class RateLimitInvalidStub extends RestStubBase
{
    /** @var array<string, mixed> */
    protected array $policy = [
        'csrf'      => false,
        'audit'     => false,
        'rateLimit' => 123,
    ];
}

class ValidationRequiredStub extends RestStubBase
{
    /** @var array<int, array<string, mixed>> */
    protected $paramSpecs = [
        [
            'name'     => 'title',
            'type'     => 'string',
            'required' => true,
            'source'   => 'request',
        ],
    ];
}

class ValidationStrictIntStub extends RestStubBase
{
    /** @var array<int, array<string, mixed>> */
    protected $paramSpecs = [
        [
            'name'     => 'count',
            'type'     => 'int',
            'required' => true,
            'strict'   => true,
            'source'   => 'request',
        ],
    ];
}

class ValidationJsonSourceStub extends RestStubBase
{
    /** @var array<int, array<string, mixed>> */
    protected $paramSpecs = [
        [
            'name'     => 'label',
            'type'     => 'string',
            'required' => true,
            'source'   => 'json',
        ],
    ];

    /**
     * @return array<string, string>|null
     */
    protected function getJsonBody()
    {
        return ['label' => 'from-json'];
    }
}

class ValidationArrayItemsStub extends RestStubBase
{
    /** @var array<int, array<string, mixed>> */
    protected $paramSpecs = [
        [
            'name'     => 'tags',
            'type'     => 'array',
            'required' => true,
            'source'   => 'request',
            'items'    => [
                'type' => 'string',
            ],
        ],
    ];
}

class SuccessEnvelopeStub extends RestStubBase
{
    /**
     * @return array<string, mixed>
     */
    protected function process()
    {
        return [
            'data'   => ['id' => 42, 'name' => 'sample'],
            'status' => 'SUCCESS',
        ];
    }
}

class NonSuccessStatusStub extends RestStubBase
{
    /**
     * @return array<string, mixed>
     */
    protected function process()
    {
        return [
            'data'   => ['exists' => true],
            'status' => 'TEAM_EXISTS',
        ];
    }
}

class AuthenticatedSuccessStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Authenticated;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'read',
        'visibilityAware' => false,
    ];
}

class OwnerGrantedStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Owner;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => 'id',
        'operation'       => 'read',
        'visibilityAware' => false,
    ];

    protected function checkOwnership(): bool
    {
        return true;
    }
}

class AdminGrantedStub extends RestStubBase
{
    protected SecurityLevel $securityLevel = SecurityLevel::Admin;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'            => true,
        'public'          => false,
        'resource'        => 'test',
        'resourceIdParam' => null,
        'operation'       => 'admin',
        'visibilityAware' => false,
    ];
}
