<?php

namespace App\Support;

/**
 * Helper class for generating consistent cache keys.
 */
class CacheKey
{
    /**
     * Generate a cache key for a model with optional scope and parameters.
     *
     * Pattern: models.{model}.{scope}.{param1}.{value1}...
     *
     * @param  string  $model  Model name (e.g., 'genre')
     * @param  string  $scope  Scope name (e.g., 'all', 'options', 'id')
     * @param  array<string, mixed>  $params  Additional parameters as key-value pairs
     */
    public static function forModel(string $model, string $scope, array $params = []): string
    {
        $key = "models.{$model}.{$scope}";

        foreach ($params as $param => $value) {
            $key .= ".{$param}.{$value}";
        }

        return $key;
    }
}
