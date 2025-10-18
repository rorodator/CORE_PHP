<?php
namespace Core\Functional;

/**
 * Concrete client targeting JIRA REST API v2 endpoints.
 *
 * Exposes typed operations built on top of the base HTTP helpers.
 */
class JiraClientV2 extends JiraClientBase
{
    /**
     * Fetches an issue by key using JIRA REST API v2.
     *
     * @param string $key Issue key, e.g., "PROJ-123".
     * @return mixed Decoded JSON issue payload.
     * @throws \RuntimeException When the client is not configured or request fails.
     */
    public function getIssue(string $key)
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('JiraClient not configured');
        }
        return $this->httpGet('/rest/api/2/issue/' . rawurlencode($key));
    }
}

?>

