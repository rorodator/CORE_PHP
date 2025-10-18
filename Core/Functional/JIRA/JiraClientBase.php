<?php
namespace Core\Functional;

/**
 * Abstract base class providing configuration and low-level HTTP helpers for JIRA clients.
 *
 * Responsibilities:
 * - Load configuration from the global core config (section "jira") or from constructor options
 * - Manage authentication via API token or username/password
 * - Provide reusable HTTP helpers (headers, GET)
 */
abstract class JiraClientBase implements JiraClientInterface
{
    protected string $baseUrl = '';
    protected ?string $username = null;
    protected ?string $password = null;
    protected ?string $token = null; // PAT ou token API

    /**
     * @param array|null $options Optional runtime configuration to override config file values.
     *                            Supported keys: base_url, username, password, token.
     */
    public function __construct(?array $options = null)
    {
        $cfg = function_exists('core') ? core()->getConfigSection('jira') : [];
        $options = $options ?? [];

        $this->baseUrl = $options['base_url'] ?? ($cfg['base_url'] ?? '');
        $this->username = $options['username'] ?? ($cfg['username'] ?? null);
        $this->password = $options['password'] ?? ($cfg['password'] ?? null);
        $this->token    = $options['token']    ?? ($cfg['token']    ?? null);
    }

    public function isConfigured(): bool
    {
        if (empty($this->baseUrl)) return false;
        // Auth possible via token ou via user/pass
        return !empty($this->token) || (!empty($this->username) && !empty($this->password));
    }

    /**
     * Builds HTTP headers based on configured authentication method.
     *
     * @return array List of HTTP header strings.
     */
    protected function buildHeaders(): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        } elseif (!empty($this->username) && !empty($this->password)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
        }
        return $headers;
    }

    /**
     * Executes an HTTP GET request against the JIRA server.
     *
     * @param string $path  Relative API path (e.g., "/rest/api/2/issue/PROJ-1").
     * @param array  $query Optional query parameters to be appended to the URL.
     * @return mixed Decoded JSON payload.
     * @throws \RuntimeException When the request fails or an HTTP error is returned.
     */
    protected function httpGet(string $path, array $query = [])
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP GET failed: ' . $err);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($response, true);
        if ($status >= 400) {
            $message = is_array($data) && isset($data['errorMessages']) ? implode('; ', $data['errorMessages']) : ('HTTP ' . $status);
            throw new \RuntimeException('JIRA error: ' . $message);
        }
        return $data;
    }
}

?>

