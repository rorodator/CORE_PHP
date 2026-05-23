<?php
namespace Core\Exception;

/**
 * Raised when a REST endpoint cannot be served because of a security
 * declaration issue or an authorization failure.
 *
 * The carried HTTP status drives the response code:
 * - 401 → authentication required
 * - 403 → forbidden (auth ok but missing rights / ownership)
 * - 405 → method not allowed
 * - 500 → server-side configuration issue (e.g. missing security declaration)
 *
 * The message is intended for logs only — public responses must remain terse.
 */
class CoreSecurityException extends \RuntimeException
{
    /**
     * Suggested HTTP status to surface to the client.
     */
    private int $httpStatus;

    /**
     * Optional functional status code returned in the JSON payload.
     */
    private string $functionalStatus;

    /**
     * @param string          $message          Human-readable cause (logs only).
     * @param int             $httpStatus       HTTP status to return (default 403).
     * @param string          $functionalStatus Functional status (e.g. FORBIDDEN, UNAUTHENTICATED).
     * @param \Throwable|null $previous         Optional previous exception.
     */
    public function __construct(
        string $message,
        int $httpStatus = 403,
        string $functionalStatus = 'FORBIDDEN',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->functionalStatus = $functionalStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getFunctionalStatus(): string
    {
        return $this->functionalStatus;
    }
}
