<?php
namespace Core\Base;

/**
 * REST endpoint security levels.
 *
 * The default sentinel `Undefined` forces every concrete RestService to
 * declare an explicit level; failure to do so raises a CoreSecurityException
 * before any business logic runs (deny-by-default).
 *
 * Levels:
 * - Undefined     : sentinel — must be replaced by an explicit value.
 * - Public        : open to anonymous callers, no auth required.
 * - Authenticated : caller must have an authenticated session (any role).
 * - Owner         : authenticated AND owner of the targeted resource (delegated to checkOwnership()).
 * - Shared        : authenticated AND has explicit shared access (delegated to checkSharedAccess()).
 * - Admin         : authenticated AND has the `admin` role.
 * - Ai            : authenticated AND allowed to invoke AI features (delegated to checkAiAccess()).
 */
enum SecurityLevel: string
{
    case Undefined     = 'undefined';
    case Public        = 'public';
    case Authenticated = 'authenticated';
    case Owner         = 'owner';
    case Shared        = 'shared';
    case Admin         = 'admin';
    case Ai            = 'ai';
}
