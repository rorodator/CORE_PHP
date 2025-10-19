<?php
namespace Core\Base;

/**
 * Class Lang
 *
 * Language management service for handling internationalization.
 * Manages language selection, label caching, and provides translation functionality.
 * Supports dynamic language switching and label interpolation.
 */
class Lang {
    /**
     * Current language code
     * @var string
     */
    private $currentLang = 'fr';

    /**
     * Available languages
     * @var array
     */
    private $availableLangs = ['fr', 'en'];

    /**
     * Cache of loaded labels by language
     * @var array
     */
    private $labelCache = [];

    /**
     * Path to language files directory
     * @var string
     */
    private $langPath;

    /**
     * Constructor
     * Gets configuration from MyManager.ini like other Core/Base services
     */
    public function __construct() {
        // Get configuration from core()
        $langConf = [];
        if (core() && core()->getConfigSection('lang')) {
            $langConf = core()->getConfigSection('lang');
        }

        // Set language path from config or default
        $this->langPath = isset($langConf['path']) ? $langConf['path'] : __DIR__ . '/../../LANG/';
        
        // Set default language from config or default
        $this->currentLang = isset($langConf['default']) ? $langConf['default'] : 'fr';
        
        // Set available languages from config or default
        if (isset($langConf['available'])) {
            $this->availableLangs = explode(',', $langConf['available']);
            $this->availableLangs = array_map('trim', $this->availableLangs);
        }
    }

    /**
     * Set the current language
     *
     * @param string $lang Language code (fr, en)
     * @return bool True if language was set successfully
     */
    public function setLang($lang) {
        if (in_array($lang, $this->availableLangs)) {
            $this->currentLang = $lang;
            return true;
        }
        return false;
    }

    /**
     * Get the current language
     *
     * @return string Current language code
     */
    public function getCurrentLang() {
        return $this->currentLang;
    }

    /**
     * Get available languages
     *
     * @return array Array of available language codes
     */
    public function getAvailableLangs() {
        return $this->availableLangs;
    }

    /**
     * Load labels for a specific language
     *
     * @param string $lang Language code
     * @return array Loaded labels
     * @throws \Exception If language file cannot be loaded
     */
    private function loadLabels($lang) {
        if (isset($this->labelCache[$lang])) {
            return $this->labelCache[$lang];
        }

        $langFile = $this->langPath . "labels-{$lang}.json";
        
        if (!file_exists($langFile)) {
            throw new \Exception("Language file not found: $langFile");
        }

        $content = file_get_contents($langFile);
        $labels = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in language file: $langFile - " . json_last_error_msg());
        }

        $this->labelCache[$lang] = $labels;
        return $labels;
    }

    /**
     * Get a label by key with optional interpolation
     *
     * @param string $key Label key (e.g., 'errors.access_denied' or 'team.title')
     * @param array $params Parameters for interpolation
     * @param string|null $lang Language code (uses current if null)
     * @return string Translated label or key if not found
     */
    public function getLabel($key, $params = [], $lang = null) {
        $targetLang = $lang ?: $this->currentLang;
        
        try {
            $labels = $this->loadLabels($targetLang);
        } catch (\Exception $e) {
            // Fallback to default language if current fails
            if ($targetLang !== 'fr') {
                try {
                    $labels = $this->loadLabels('fr');
                } catch (\Exception $e2) {
                    return $key; // Return key if all fails
                }
            } else {
                return $key;
            }
        }

        // Navigate through nested keys (e.g., 'errors.access_denied')
        $keys = explode('.', $key);
        $value = $labels;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Return key if not found
            }
        }

        // Handle string interpolation
        if (is_string($value) && !empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $value = str_replace('{' . $paramKey . '}', $paramValue, $value);
            }
        }

        return $value;
    }

    /**
     * Get all labels for a specific language
     *
     * @param string|null $lang Language code (uses current if null)
     * @return array All labels for the language
     */
    public function getAllLabels($lang = null) {
        $targetLang = $lang ?: $this->currentLang;
        
        try {
            return $this->loadLabels($targetLang);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a label exists for the current language
     *
     * @param string $key Label key
     * @param string|null $lang Language code (uses current if null)
     * @return bool True if label exists
     */
    public function hasLabel($key, $lang = null) {
        $targetLang = $lang ?: $this->currentLang;
        
        try {
            $labels = $this->loadLabels($targetLang);
        } catch (\Exception $e) {
            return false;
        }

        $keys = explode('.', $key);
        $value = $labels;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear the label cache for a specific language or all languages
     *
     * @param string|null $lang Language code (clears all if null)
     */
    public function clearCache($lang = null) {
        if ($lang) {
            unset($this->labelCache[$lang]);
        } else {
            $this->labelCache = [];
        }
    }

    /**
     * Get language display name
     *
     * @param string $lang Language code
     * @return string Display name for the language
     */
    public function getLangDisplayName($lang) {
        $displayNames = [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch'
        ];
        return isset($displayNames[$lang]) ? $displayNames[$lang] : $lang;
    }

    /**
     * Switch to the next available language
     *
     * @return string New current language
     */
    public function switchToNextLang() {
        $currentIndex = array_search($this->currentLang, $this->availableLangs);
        $nextIndex = ($currentIndex + 1) % count($this->availableLangs);
        $this->setLang($this->availableLangs[$nextIndex]);
        return $this->currentLang;
    }
}
?>
