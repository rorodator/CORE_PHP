<?php
namespace Core\Base;

/**
 * Class Core
 *
 * Central Core singleton class that serves as the main service container.
 * Provides access to services and configuration loaded from config.ini.
 * Implements the Service Locator pattern for dependency injection.
 */
class Core {
    /**
     * Singleton instance of Core
     * @var Core|null
     */
    private static $instance = null;

    /**
     * Parsed configuration from INI file
     * @var array
     */
    private $config;

    /**
     * Cache of instantiated services (lazy loading)
     * @var array
     */
    public $magicContent = [];

    /**
     * Service factory configuration from INI [services] section
     * @var array
     */
    private $serviceFactory;

    /**
     * Private constructor for singleton pattern
     *
     * @param string|null $configIniPath Path to the configuration INI file
     * @param array|null  $preloadedConfig Pre-parsed config (skips INI read when set)
     * @throws \Exception If config file is not readable
     */
    private function __construct($configIniPath = null, ?array $preloadedConfig = null) {
        if ($preloadedConfig !== null) {
            $this->config = $preloadedConfig;
        } else {
            if ($configIniPath === null || !is_readable($configIniPath)) {
                throw new \Exception("Config file not found or not readable: $configIniPath");
            }
            $this->config = parse_ini_file($configIniPath, true);
        }
        $this->serviceFactory = isset($this->config['services']) ? $this->config['services'] : [];
    }

    /**
     * Boot Core from a pre-merged configuration array (environment overlays).
     *
     * @param array $config Full merged INI structure (sections as keys).
     * @return Core
     */
    public static function bootFromConfig(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self(null, $config);
        }
        return self::$instance;
    }

    /**
     * Deep-merge INI-style section arrays (overlay wins on leaf keys).
     *
     * @param array $base
     * @param array $overlay
     * @return array
     */
    public static function mergeConfig(array $base, array $overlay): array
    {
        foreach ($overlay as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            if (!isset($base[$section]) || !is_array($base[$section])) {
                $base[$section] = $values;
                continue;
            }
            $base[$section] = array_merge($base[$section], $values);
        }
        return $base;
    }

    /**
     * Get the singleton instance of Core
     *
     * @param string|null $configIniPath Path to config file (required on first call)
     * @return Core The singleton instance
     * @throws \Exception If config path not provided on first call
     */
    public static function getInstance($configIniPath = null) {
        if (self::$instance === null) {
            if ($configIniPath === null) {
                throw new \Exception("First call to Core::getInstance() must provide config path.");
            }
            self::$instance = new self($configIniPath);
        }
        return self::$instance;
    }

    /**
     * Get a configuration section from the INI file
     *
     * @param string $section Section name to retrieve
     * @return array Configuration section data
     */
    public function getConfigSection($section) {
        return isset($this->config[$section]) ? $this->config[$section] : [];
    }

    /**
     * Magic getter for lazy service instantiation
     * 
     * @param string $name Service name
     * @return mixed The requested service instance or null if not found
     * @throws \Exception If service class is not found
     */
    public function __get($name) {
        if (!isset($this->magicContent[$name])) {
            $target = null;
            if (isset($this->serviceFactory[$name])) {
                $className = $this->serviceFactory[$name];
                if (class_exists($className)) {
                    $target = new $className();
                } else {
                    throw new \Exception("Service class [$className] for label [$name] not found.");
                }
            }
            if ($target) {
                $this->magicContent[$name] = $target;
            }
        }
        return isset($this->magicContent[$name]) ? $this->magicContent[$name] : null;
    }

    /**
     * Get a specific configuration value with optional default
     *
     * @param string $section Configuration section name
     * @param string $key Configuration key name
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    public function getConfigValue($section, $key, $default = null) {
        return isset($this->config[$section][$key]) ? $this->config[$section][$key] : $default;
    }
}

/**
 * Global function to access the Core singleton instance
 *
 * @param string|null $configIniPath Optional config path for first initialization
 * @return Core The Core singleton instance
 */
function core($configIniPath = null) {
    return Core::getInstance($configIniPath);
}



