<?php
namespace Core\Base;

/**
 * Allowed HTTP methods for REST endpoints.
 *
 * Each RestService must declare its expected method; a mismatched
 * incoming request is rejected with HTTP 405 before any business
 * logic runs.
 */
enum HttpMethod: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Patch  = 'PATCH';
    case Delete = 'DELETE';
}
