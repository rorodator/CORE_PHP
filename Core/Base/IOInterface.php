<?php
namespace Core\Base;

/**
 * Interface IOInterface
 *
 * Defines the contract for Input/Output objects.
 * All specialized IO classes should implement this interface to ensure
 * consistent behavior and allow for easy substitution of data sources.
 */
interface IOInterface
{
    /**
     * Get the data source identifier (e.g., 'database', 'api', 'cache')
     * 
     * @return string The data source type
     */
    public function getDataSourceType(): string;

    /**
     * Check if the IO object is properly configured and ready to use
     * 
     * @return bool True if the IO object is ready, false otherwise
     */
    public function isReady(): bool;

    /**
     * Get configuration information for this IO object
     * 
     * @return array Configuration data
     */
    public function getConfig(): array;
}
?>
