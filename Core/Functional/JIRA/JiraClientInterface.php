<?php
namespace Core\Functional;

/**
 * Interface contract for JIRA clients.
 * Implementations must provide configuration checks and expose typed API operations.
 */
interface JiraClientInterface
{
    /**
     * Indicates whether the client is ready to perform authenticated HTTP calls.
     * This typically requires a non-empty base URL and a valid authentication method
     * (either API token or username/password).
     *
     * @return bool True if the client has sufficient configuration to operate.
     */
    public function isConfigured(): bool;

    /**
     * Retrieves a JIRA issue using its key (e.g., "PROJ-123").
     *
     * @param string $key Issue key to retrieve.
     * @return mixed Decoded JSON response for the issue.
     */
    public function getIssue(string $key);
}

?>

