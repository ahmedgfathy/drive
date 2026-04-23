<?php

namespace App\Services\Auth;

use RuntimeException;

class ActiveDirectoryService
{
    /**
     * Attempt to authenticate a user by sAMAccountName against Active Directory.
     *
     * @return array<string, string>|null
     */
    public function authenticateBySamAccountName(string $samAccountName, string $password): ?array
    {
        $samAccountName = trim($samAccountName);
        $password = (string) $password;

        if ($samAccountName === '' || $password === '') {
            return null;
        }

        if (! extension_loaded('ldap')) {
            throw new RuntimeException('LDAP extension is not installed on the server.');
        }

        $config = config('services.active_directory');
        $host = trim((string) ($config['host'] ?? ''));
        $baseDn = trim((string) ($config['base_dn'] ?? ''));
        $bindUsername = trim((string) ($config['bind_username'] ?? ''));
        $bindPassword = (string) ($config['bind_password'] ?? '');
        $port = (int) ($config['port'] ?? 389);
        $scheme = trim((string) ($config['scheme'] ?? 'ldap'));
        $timeout = (int) ($config['timeout'] ?? 5);

        if ($host === '' || $baseDn === '' || $bindUsername === '' || $bindPassword === '') {
            throw new RuntimeException('Active Directory configuration is incomplete.');
        }

        $connection = $this->connect($scheme, $host, $port, $timeout);

        try {
            $this->bind($connection, $bindUsername, $bindPassword);

            $filter = sprintf(
                '(&(objectCategory=person)(objectClass=user)(sAMAccountName=%s))',
                ldap_escape($samAccountName, '', LDAP_ESCAPE_FILTER)
            );

            $attributes = [
                'cn',
                'displayname',
                'mail',
                'samaccountname',
                'userprincipalname',
                'employeeid',
            ];

            $search = @ldap_search($connection, $baseDn, $filter, $attributes);
            if ($search === false) {
                throw new RuntimeException('Active Directory user search failed.');
            }

            $entries = ldap_get_entries($connection, $search);
            if (! is_array($entries) || (int) ($entries['count'] ?? 0) < 1) {
                return null;
            }

            /** @var array<string, mixed> $entry */
            $entry = $entries[0];
            $dn = (string) ($entry['dn'] ?? '');

            if ($dn === '') {
                throw new RuntimeException('Active Directory user record did not include a DN.');
            }

            $userConnection = $this->connect($scheme, $host, $port, $timeout);

            try {
                if (! @ldap_bind($userConnection, $dn, $password)) {
                    return null;
                }
            } finally {
                ldap_unbind($userConnection);
            }

            return [
                'employee_id' => $this->firstValue($entry, 'employeeid') ?: $samAccountName,
                'display_name' => $this->firstValue($entry, 'displayname')
                    ?: $this->firstValue($entry, 'cn')
                    ?: $samAccountName,
                'email' => $this->firstValue($entry, 'mail')
                    ?: $this->firstValue($entry, 'userprincipalname')
                    ?: $samAccountName.'@pms.local',
                'samaccountname' => $this->firstValue($entry, 'samaccountname'),
                'userprincipalname' => $this->firstValue($entry, 'userprincipalname'),
                'dn' => $dn,
            ];
        } finally {
            ldap_unbind($connection);
        }
    }

    /**
     * @return resource
     */
    private function connect(string $scheme, string $host, int $port, int $timeout)
    {
        $uri = sprintf('%s://%s:%d', $scheme !== '' ? $scheme : 'ldap', $host, $port);
        $connection = @ldap_connect($uri);

        if ($connection === false) {
            throw new RuntimeException('Unable to connect to the Active Directory server.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $timeout);

        return $connection;
    }

    /**
     * @param  resource  $connection
     */
    private function bind($connection, string $username, string $password): void
    {
        if (! @ldap_bind($connection, $username, $password)) {
            throw new RuntimeException('Failed to bind to Active Directory with the configured service account.');
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function firstValue(array $entry, string $attribute): string
    {
        $attribute = strtolower($attribute);
        $value = $entry[$attribute][0] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
