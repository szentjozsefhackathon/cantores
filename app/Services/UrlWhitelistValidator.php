<?php

namespace App\Services;

use App\Models\WhitelistRule;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UrlWhitelistValidator
{
    /**
     * Validate a URL against whitelist rules.
     *
     * @param  string  $url  The URL to validate
     * @return bool True if URL passes all validation checks and matches a whitelist rule
     *
     * @throws InvalidArgumentException If URL cannot be parsed or is malformed
     */
    public function validate(string $url): bool
    {
        // Parse URL and validate basic structure
        $components = $this->parseAndValidateUrl($url);

        // Check for matching whitelist rules
        $matchingRules = WhitelistRule::query()
            ->active()
            ->forHostname($components['host'])
            ->where('scheme', $components['scheme'])
            ->get();

        if ($matchingRules->isEmpty()) {
            Log::debug('No whitelist rule found for hostname', [
                'hostname' => $components['host'],
                'scheme' => $components['scheme'],
                'url' => $url,
            ]);

            return false;
        }

        // Check each rule for path prefix match and port validation
        foreach ($matchingRules as $rule) {
            if ($this->ruleMatches($rule, $components)) {
                Log::debug('URL matched whitelist rule', [
                    'url' => $url,
                    'rule_id' => $rule->id,
                    'hostname' => $rule->hostname,
                    'path_prefix' => $rule->path_prefix,
                ]);

                return true;
            }
        }

        Log::debug('URL did not match any whitelist rule', [
            'url' => $url,
            'hostname' => $components['host'],
            'scheme' => $components['scheme'],
            'path' => $components['path'] ?? '/',
        ]);

        return false;
    }

    /**
     * Parse URL and validate required components.
     *
     * @return array<string, mixed> Parsed URL components
     *
     * @throws InvalidArgumentException
     */
    private function parseAndValidateUrl(string $url): array
    {
        $components = parse_url($url);

        if ($components === false) {
            throw new InvalidArgumentException("Unable to parse URL: {$url}");
        }

        // Ensure required components are present
        if (empty($components['scheme'])) {
            throw new InvalidArgumentException("URL must have a scheme (http/https): {$url}");
        }

        if (empty($components['host'])) {
            throw new InvalidArgumentException("URL must have a hostname: {$url}");
        }

        // Scheme must be http or https
        $scheme = strtolower($components['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("URL scheme must be 'http' or 'https': {$url}");
        }

        // Hostname must be non-empty (already checked)
        $host = strtolower($components['host']);

        // No userinfo allowed
        if (isset($components['user']) || isset($components['pass'])) {
            throw new InvalidArgumentException("URL must not contain userinfo (username:password@): {$url}");
        }

        // Port validation
        $port = $components['port'] ?? null;
        if ($port !== null) {
            $expectedPort = $scheme === 'http' ? 80 : 443;
            if ($port != $expectedPort) {
                // Port validation will be handled by rule matching (allow_any_port)
                // We'll just note it here
            }
        }

        // Normalize path
        $path = $components['path'] ?? '/';
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $query = $components['query'] ?? null;

        if ($query !== null && $query !== '') {
            // Parse query into an array of keys/values
            parse_str($query, $params);

            // Normalize keys to lowercase for comparison
            $keys = array_map('strtolower', array_keys($params));

            // Block common redirector parameters
            $blocked = [
                'next',
                'url',
                'redirect',
                'redirect_url',
                'redirecturi',
                'redirect_uri',
                'return',
                'returnto',
                'continue',
                'dest',
                'destination',
                'target',
                'to',
            ];

            foreach ($blocked as $bad) {
                if (in_array($bad, $keys, true)) {
                    throw new InvalidArgumentException("URL must not contain '{$bad}' query parameter: {$url}");
                }
            }
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query,
            'fragment' => $components['fragment'] ?? null,
            'original_url' => $url,
        ];
    }

    /**
     * Check if a rule matches the URL components.
     *
     * @param  array<string, mixed>  $components
     */
    private function ruleMatches(WhitelistRule $rule, array $components): bool
    {
        // Scheme already matched in query
        // Hostname already matched in query

        // Check port validation
        if (! $this->validatePort($rule, $components['port'], $components['scheme'])) {
            return false;
        }

        // Check path prefix
        return $this->validatePathPrefix($rule, $components['path']);
    }

    /**
     * Validate port against rule.
     */
    private function validatePort(WhitelistRule $rule, ?int $port, string $scheme): bool
    {
        // If no port specified, it's using default port for scheme
        if ($port === null) {
            return true;
        }

        // If rule allows any port, accept it
        if ($rule->allow_any_port) {
            return true;
        }

        // Otherwise port must match default for scheme
        $expectedPort = $scheme === 'http' ? 80 : 443;

        return $port == $expectedPort;
    }

    /**
     * Validate path prefix.
     */
    private function validatePathPrefix(WhitelistRule $rule, string $path): bool
    {
        $prefix = $rule->path_prefix;

        // Ensure prefix starts with slash
        if (! str_starts_with($prefix, '/')) {
            $prefix = '/'.$prefix;
        }

        // Exact match or prefix match
        return $path === $prefix || str_starts_with($path, $prefix);
    }

    /**
     * Get all allowed hosts for error messages.
     *
     * @return array<string>
     */
    public function getAllowedHosts(): array
    {
        return WhitelistRule::query()
            ->active()
            ->distinct()
            ->pluck('hostname')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get a descriptive error message for validation failures.
     */
    public function getErrorMessage(string $url): string
    {
        $allowedHosts = $this->getAllowedHosts();

        if (empty($allowedHosts)) {
            return 'The URL must be whitelisted. No whitelist rules are configured.';
        }

        $hostList = implode(', ', $allowedHosts);

        return "The URL must be whitelisted. Allowed hosts: {$hostList}";
    }
}
