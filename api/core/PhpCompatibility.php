<?php
if (!extension_loaded('mbstring')) {
    require_once dirname(__DIR__) . '/vendor/polyfill-mbstring/Mbstring.php';
    require_once dirname(__DIR__) . '/vendor/polyfill-mbstring/bootstrap.php';
}
/** Runtime compatibility helpers; no KG or answer-generation policy lives here. */
final class PhpCompatibility {
    public static function value($value, $type, $nullable = false) {
        if ($value === null && $nullable) return null;
        $valid = false;
        switch ($type) {
            case 'string':
                $valid = is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
                if ($valid) return (string)$value;
                break;
            case 'int':
            case 'float':
                $valid = is_int($value) || is_float($value) || is_bool($value) || (is_string($value) && is_numeric($value));
                if ($valid) return $type === 'int' ? (int)$value : (float)$value;
                break;
            case 'bool':
                if (is_scalar($value)) return (bool)$value;
                break;
            case 'array': $valid = is_array($value); break;
            case 'callable': $valid = is_callable($value); break;
            default: $valid = is_object($value) && is_a($value, $type);
        }
        if (!$valid) {
            if (class_exists('TypeError', false)) throw new TypeError('Expected ' . $type);
            throw new InvalidArgumentException('Expected ' . $type);
        }
        return $value;
    }

    public static function compare($left, $right) {
        return $left == $right ? 0 : ($left < $right ? -1 : 1);
    }

    public static function endsWith($value, $suffix) {
        return $suffix === '' || substr($value, -strlen($suffix)) === $suffix;
    }

    public static function jsonEncode($value, $flags = 0, $depth = 512) {
        $encoded = json_encode($value, $flags, $depth);
        self::checkJson();
        return $encoded;
    }

    public static function jsonDecode($value, $associative = false, $depth = 512, $flags = 0) {
        $decoded = json_decode($value, $associative, $depth, $flags);
        self::checkJson();
        return $decoded;
    }

    private static function checkJson() {
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            if (class_exists('JsonException', false)) throw new JsonException(json_last_error_msg(), $error);
            throw new UnexpectedValueException(json_last_error_msg(), $error);
        }
    }
}
