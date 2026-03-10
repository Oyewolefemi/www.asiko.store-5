<?php
/**
 * EnvLoader - Simple environment variable loader for PHP
 * Loads variables from .env file and makes them available via EnvLoader::get()
 */
class EnvLoader
{
    /**
     * Array to store loaded environment variables
     */
    private static $variables = [];
    
    /**
     * Flag to track if environment has been loaded
     */
    private static $loaded = false;

    /**
     * Load environment variables from a .env file
     * 
     * @param string $filePath Path to the .env file
     * @return bool True if file was loaded successfully, false otherwise
     */
    public static function load($filePath)
    {
        if (!file_exists($filePath)) {
            error_log("EnvLoader: .env file not found at: " . $filePath);
            return false;
        }

        if (!is_readable($filePath)) {
            error_log("EnvLoader: .env file is not readable: " . $filePath);
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            error_log("EnvLoader: Failed to read .env file: " . $filePath);
            return false;
        }

        foreach ($lines as $line) {
            // Skip comments and empty lines
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                // Store in our variables array
                self::$variables[$key] = $value;
                
                // Also set as PHP environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
        return true;
    }

    /**
     * Get an environment variable value
     * 
     * @param string $key The environment variable key
     * @param mixed $default Default value if key is not found
     * @return mixed The environment variable value or default
     */
    public static function get($key, $default = null)
    {
        // Check our loaded variables first
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        // Check PHP's environment variables
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        // Check $_ENV superglobal
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Return default value
        return $default;
    }

    /**
     * Check if a specific environment variable exists
     * 
     * @param string $key The environment variable key
     * @return bool True if the variable exists, false otherwise
     */
    public static function has($key)
    {
        return isset(self::$variables[$key]) || 
               getenv($key) !== false || 
               isset($_ENV[$key]);
    }

    /**
     * Get all loaded environment variables
     * 
     * @return array Array of all environment variables
     */
    public static function all()
    {
        return array_merge($_ENV, self::$variables);
    }

    /**
     * Check if environment has been loaded
     * 
     * @return bool True if environment has been loaded
     */
    public static function isLoaded()
    {
        return self::$loaded;
    }

    /**
     * Clear all loaded environment variables
     * Useful for testing purposes
     */
    public static function clear()
    {
        self::$variables = [];
        self::$loaded = false;
    }

    /**
     * Set an environment variable programmatically
     * 
     * @param string $key The environment variable key
     * @param mixed $value The environment variable value
     */
    public static function set($key, $value)
    {
        self::$variables[$key] = $value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}