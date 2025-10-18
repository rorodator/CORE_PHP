<?php
namespace Core\Base;

/**
 * Class IO
 *
 * Input/Output abstraction layer for data access.
 * Provides dynamic access to specialized IO objects through mapping configuration.
 * Allows substitution of data sources without changing business logic.
 */
class IO
{
    /**
     * Cache of instantiated IO objects (lazy loading)
     * @var array
     */
    private $ioObjects = [];

    /**
     * IO mapping configuration from INI [IO] section
     * @var array
     */
    private $ioMapping = [];

    /**
     * Initialize IO with mapping configuration
     * If no mapping provided, attempts to load from Core configuration
     *
     * @param array $ioMapping IO class mapping from configuration (optional)
     */
    public function __construct(array $ioMapping = [])
    {
        // If no mapping provided, try to get it from Core configuration
        if (empty($ioMapping) && function_exists('core') && core()) {
            $ioMapping = core()->getConfigSection('IO');
        }
        
        $this->ioMapping = $ioMapping;
    }

    /**
     * Magic getter for dynamic IO object access
     * 
     * @param string $name IO object name (e.g., 'user', 'team')
     * @return mixed The requested IO object instance or null if not found
     * @throws \Exception If IO class is not found
     */
    public function __get($name)
    {
        if (!isset($this->ioObjects[$name])) {
            $target = null;
            if (isset($this->ioMapping[$name])) {
                $className = $this->ioMapping[$name];
                if (class_exists($className)) {
                    $target = new $className();
                } else {
                    throw new \Exception("IO class [$className] for object [$name] not found.");
                }
            }
            if ($target) {
                $this->ioObjects[$name] = $target;
            }
        }
        return isset($this->ioObjects[$name]) ? $this->ioObjects[$name] : null;
    }

    /**
     * Check if an IO object is available
     *
     * @param string $name IO object name
     * @return bool True if IO object is configured and available
     */
    public function has($name)
    {
        return isset($this->ioMapping[$name]) && class_exists($this->ioMapping[$name]);
    }

    /**
     * Get all available IO object names
     *
     * @return array List of configured IO object names
     */
    public function getAvailableObjects()
    {
        return array_keys($this->ioMapping);
    }
}
?>
