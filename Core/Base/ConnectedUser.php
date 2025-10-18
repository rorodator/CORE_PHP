<?php
namespace Core\Base;

/**
 * Class ConnectedUser
 *
 * Represents a generic connected user in the Core system.
 * Provides basic user information including authentication status,
 * login credentials, roles, and session token.
 * This is the base class that can be extended by application-specific user classes.
 */
class ConnectedUser
{
    /**
     * User unique identifier
     * @var int|null
     */
    protected $id;

    /**
     * User login identifier (typically email or username)
     * @var string|null
     */
    protected $login;

    /**
     * Array of user roles/permissions
     * @var array
     */
    protected $roles = [];

    /**
     * Authentication token for the user session
     * @var string|null
     */
    protected $token;

    /**
     * Initialize the ConnectedUser with data from array or session
     *
     * @param array|null $data User data array. If null, attempts to load from session
     */
    public function __construct(?array $data = null)
    {
        if ($data === null && function_exists('core') && core()->session) {
            $data = core()->session->get('user');
        }
        $this->id    = $data['id']    ?? null;
        $this->login = $data['login'] ?? null;
        $this->roles = $data['roles'] ?? [];
        $this->token = $data['token'] ?? null;
    }

    /**
     * Get the user ID
     *
     * @return int|null
     */
    public function getId() { return $this->id; }

    /**
     * Get the user login identifier
     *
     * @return string|null
     */
    public function getLogin() { return $this->login; }

    /**
     * Get all user roles
     *
     * @return array
     */
    public function getRoles() { return $this->roles; }

    /**
     * Check if user has a specific role
     *
     * @param string $role Role to check for
     * @return bool
     */
    public function hasRole($role) { return in_array($role, $this->roles); }

    /**
     * Get the user authentication token
     *
     * @return string|null
     */
    public function getToken() { return $this->token; }

    /**
     * Check if user is authenticated (has valid ID)
     *
     * @return bool
     */
    public function isAuthenticated() { return !empty($this->id); }

    /**
     * Convert user data to array format
     *
     * @return array User data as associative array
     */
    public function toArray()
    {
        return [
            'id'    => $this->id,
            'login' => $this->login,
            'roles' => $this->roles,
            'token' => $this->token,
        ];
    }

    /**
     * Create ConnectedUser instance from array data
     *
     * @param array $data User data array
     * @return static New ConnectedUser instance
     */
    public static function fromArray(array $data) { return new static($data); }
}
?>



