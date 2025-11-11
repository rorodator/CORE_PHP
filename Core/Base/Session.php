<?php
namespace Core\Base;

/**
 * Class Session
 *
 * Provides a simple interface for PHP session management.
 * Handles session initialization and provides methods for storing
 * and retrieving session data with automatic session start.
 */
class Session
{
    /**
     * Start a new session if none is active
     *
     * @return void
     */
    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Keep session alive across browser restarts (dev-friendly)
            // 30 days lifetime, cookie scoped to project base path
            $cookiePath = '/MyManager';
            $lifetime = 60 * 60 * 24 * 30; // 30 days
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path' => $cookiePath,
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                // Fallback for older PHP versions
                session_set_cookie_params($lifetime, $cookiePath);
            }
            session_start();
        }
    }

    /**
     * Set a session value
     *
     * @param string $key Session key
     * @param mixed $value Value to store
     * @return void
     */
    public function set($key, $value)
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value with optional default
     *
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return mixed Session value or default
     */
    public function get($key, $default = null)
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists
     *
     * @param string $key Session key to check
     * @return bool True if key exists, false otherwise
     */
    public function has($key)
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key
     *
     * @param string $key Session key to remove
     * @return void
     */
    public function remove($key)
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the current session and clear session data
     *
     * @return void
     */
    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            $_SESSION = [];
        }
    }
}
?>




