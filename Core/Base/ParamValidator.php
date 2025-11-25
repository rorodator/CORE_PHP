<?php
namespace Core\Base;

/**
 * Class ParamValidator
 *
 * Provides parameter validation functionality with support for various data types.
 * Validates parameters against type specifications including required fields,
 * min/max values, length constraints, and custom regex patterns.
 */
class ParamValidator
{
    /**
     * Map of validation types to their corresponding validator methods
     * @var array
     */
    private $typeValidators = [
        'email'  => 'validateEmail',
        'int'    => 'validateInt',
        'float'  => 'validateFloat',
        'string' => 'validateString',
        'bool'   => 'validateBool',
    ];

    /**
     * Add a custom validator method for a specific type
     *
     * @param string $type The data type name
     * @param string $method The validator method name
     * @return void
     */
    public function addValidatorToFactory($type, $method)
    {
        $this->typeValidators[$type] = $method;
    }

    /**
     * Validate a parameter value against its specification
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return mixed The validated value
     * @throws \Exception If validation fails
     */
    public function validate($value, $spec)
    {
        if (isset($spec['required']) && $spec['required'] && ($value === null || $value === '')) {
            throw new \Exception("Missing required parameter: {$spec['name']}");
        }
        if ($value === null || $value === '') {
            return $spec['default'] ?? null;
        }

        $type = $spec['type'] ?? 'string';
        $validator = $this->typeValidators[$type] ?? null;
        if ($validator && method_exists($this, $validator)) {
            $value = $this->$validator($value, $spec);
        } else {
            throw new \Exception("Unknown or unsupported type '{$type}' for parameter: {$spec['name']}");
        }

        if (isset($spec['min']) && $value < $spec['min']) {
            throw new \Exception("Value for {$spec['name']} below minimum: {$spec['min']}");
        }
        if (isset($spec['max']) && $value > $spec['max']) {
            throw new \Exception("Value for {$spec['name']} above maximum: {$spec['max']}");
        }
        if (isset($spec['regex']) && !preg_match($spec['regex'], $value)) {
            throw new \Exception("Value for {$spec['name']} does not match pattern");
        }
        return $value;
    }

    /**
     * Validate email address format
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return string The validated email
     * @throws \Exception If email format is invalid
     */
    protected function validateEmail($value, $spec) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email for parameter: {$spec['name']}");
        }
        return $value;
    }

    /**
     * Validate integer value
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return int The validated integer
     * @throws \Exception If value is not a valid integer
     */
    protected function validateInt($value, $spec) {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new \Exception("Invalid integer for parameter: {$spec['name']}");
        }
        return (int)$value;
    }

    /**
     * Validate float value
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return float The validated float
     * @throws \Exception If value is not numeric
     */
    protected function validateFloat($value, $spec) {
        if (!is_numeric($value)) {
            throw new \Exception("Invalid float for parameter: {$spec['name']}");
        }
        return (float)$value;
    }

    /**
     * Validate boolean value
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return bool The validated boolean
     * @throws \Exception If value cannot be converted to boolean
     */
    protected function validateBool($value, $spec) {
        if (is_bool($value)) {
            return $value;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            throw new \Exception("Invalid boolean for parameter: {$spec['name']}");
        }
        return $filtered;
    }

    /**
     * Validate string value with optional length constraints
     *
     * @param mixed $value The value to validate
     * @param array $spec The validation specification
     * @return string The validated string
     * @throws \Exception If string length constraints are not met
     */
    protected function validateString($value, $spec) {
        $str = (string)$value;
        if (isset($spec['minLength']) && mb_strlen($str) < $spec['minLength']) {
            throw new \Exception("String for {$spec['name']} is shorter than minimum length: {$spec['minLength']}");
        }
        if (isset($spec['maxLength']) && mb_strlen($str) > $spec['maxLength']) {
            throw new \Exception("String for {$spec['name']} is longer than maximum length: {$spec['maxLength']}");
        }
        return $str;
    }
}



