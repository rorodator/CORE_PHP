<?php
/**
 * Global Core Access Function
 * 
 * Provides convenient access to the Core singleton instance.
 * This function should be called after the Core system has been initialized.
 * 
 * @param string|null $configIniPath Path to configuration INI file (optional on subsequent calls)
 * @return \Core\Base\Core The Core singleton instance
 */
function core($configIniPath = null) {
    return \Core\Base\Core::getInstance($configIniPath);
}
?>