<?php

namespace Bernskiold\LaravelDataScrubber\Strategies;

use Bernskiold\LaravelDataScrubber\Contracts\ScrubStrategy;
use Illuminate\Database\Eloquent\Model;

class IpAnonymizeStrategy implements ScrubStrategy
{
    public function __construct(
        protected ?int $maskOctets = null,
    ) {
        $this->maskOctets ??= config('data-scrubber.strategies.ip_anonymize.mask_octets', 2);
    }

    /**
     * Apply IP anonymization - zeros out last octets for GDPR compliance.
     */
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle IPv4
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->anonymizeIpv4($value);
        }

        // Handle IPv6
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->anonymizeIpv6($value);
        }

        // Invalid IP address
        return null;
    }

    /**
     * Anonymize an IPv4 address by zeroing out the last octets.
     */
    protected function anonymizeIpv4(string $ip): string
    {
        $parts = explode('.', $ip);

        for ($i = 0; $i < $this->maskOctets; $i++) {
            $parts[3 - $i] = '0';
        }

        return implode('.', $parts);
    }

    /**
     * Anonymize an IPv6 address by zeroing out the last groups.
     */
    protected function anonymizeIpv6(string $ip): string
    {
        // Convert to binary and back to get full 8-group representation
        $packed = inet_pton($ip);

        // Unpack to 8 x 16-bit integers
        $parts = array_values(unpack('n8', $packed));

        // Mask up to 4 groups (equivalent to IPv4 octets)
        $groupsToMask = min($this->maskOctets, 4);

        for ($i = 0; $i < $groupsToMask; $i++) {
            $parts[7 - $i] = 0;
        }

        // Convert back to hex groups and use inet_ntop for proper formatting
        $hex = sprintf(
            '%04x:%04x:%04x:%04x:%04x:%04x:%04x:%04x',
            ...$parts
        );

        // Use inet_ntop for standard compression
        return inet_ntop(inet_pton($hex));
    }

    public function label(): string
    {
        return 'Anonymize IP address';
    }

    public function description(): string
    {
        return 'Zeros out the last octets of IP addresses for GDPR-compliant anonymization.';
    }
}
