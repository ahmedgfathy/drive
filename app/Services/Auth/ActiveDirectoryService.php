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

            $attributes = $this->directoryAttributes();

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

            return $this->mapDirectoryEntry($entry, $samAccountName);
        } finally {
            ldap_unbind($connection);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function searchUsers(string $term = '', int $limit = 20): array
    {
        return $this->searchDirectory($term, $limit);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listUsersByDepartment(string $department, int $limit = 250): array
    {
        $department = trim($department);

        if ($department === '') {
            return [];
        }

        $filter = sprintf(
            '(&(objectCategory=person)(objectClass=user)(department=%s))',
            ldap_escape($department, '', LDAP_ESCAPE_FILTER)
        );

        return $this->runSearch($filter, $limit);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listAllUsers(int $limit = 500): array
    {
        return $this->runSearch('(&(objectCategory=person)(objectClass=user))', $limit);
    }

    /**
     * @return array<int, string>
     */
    public function searchDepartments(string $term = '', int $limit = 12): array
    {
        $results = $term === ''
            ? $this->listAllUsers(300)
            : $this->searchDirectory($term, 80);

        $normalizedTerm = mb_strtolower(trim($term));
        $departments = [];

        foreach ($results as $entry) {
            $department = trim((string) ($entry['department'] ?? ''));
            if ($department === '') {
                continue;
            }

            if ($normalizedTerm !== '' && ! str_contains(mb_strtolower($department), $normalizedTerm)) {
                continue;
            }

            $departments[$department] = $department;
        }

        ksort($departments, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice(array_values($departments), 0, max(1, $limit));
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
     * @return array<int, array<string, string>>
     */
    private function searchDirectory(string $term, int $limit): array
    {
        $term = trim($term);

        if ($term === '') {
            return $this->listAllUsers($limit);
        }

        $escaped = ldap_escape($term, '', LDAP_ESCAPE_FILTER);
        $startsWith = $escaped.'*';
        $filter = sprintf(
            '(&(objectCategory=person)(objectClass=user)(|(displayName=%1$s)(cn=%1$s)(mail=%1$s)(sAMAccountName=%1$s)(employeeID=%1$s)(department=%1$s)(displayName=%2$s)(cn=%2$s)(mail=%2$s)(sAMAccountName=%2$s)(employeeID=%2$s)(department=%2$s)))',
            '*'.$escaped.'*',
            $startsWith
        );

        return $this->runSearch($filter, $limit);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function runSearch(string $filter, int $limit): array
    {
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

            $search = @ldap_search($connection, $baseDn, $filter, $this->directoryAttributes(), 0, max(1, $limit));
            if ($search === false) {
                throw new RuntimeException('Active Directory directory search failed.');
            }

            $entries = ldap_get_entries($connection, $search);
            if (! is_array($entries) || (int) ($entries['count'] ?? 0) < 1) {
                return [];
            }

            $results = [];
            $seen = [];

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $mapped = $this->mapDirectoryEntry($entry);
                $key = mb_strtolower(($mapped['email'] ?? '').'|'.($mapped['employee_id'] ?? '').'|'.($mapped['samaccountname'] ?? ''));

                if ($key === '||' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $results[] = $mapped;

                if (count($results) >= $limit) {
                    break;
                }
            }

            usort($results, function (array $left, array $right): int {
                return strnatcasecmp($left['display_name'] ?? '', $right['display_name'] ?? '');
            });

            return $results;
        } finally {
            ldap_unbind($connection);
        }
    }

    /**
     * @return array<int, string>
     */
    private function directoryAttributes(): array
    {
        return [
            'cn',
            'displayname',
            'mail',
            'samaccountname',
            'userprincipalname',
            'employeeid',
            'department',
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, string>
     */
    private function mapDirectoryEntry(array $entry, string $fallbackAccount = ''): array
    {
        $account = $this->firstValue($entry, 'samaccountname') ?: $fallbackAccount;

        return [
            'employee_id' => $this->firstValue($entry, 'employeeid') ?: $account,
            'display_name' => $this->firstValue($entry, 'displayname')
                ?: $this->firstValue($entry, 'cn')
                ?: $account,
            'email' => $this->firstValue($entry, 'mail')
                ?: $this->firstValue($entry, 'userprincipalname')
                ?: ($account !== '' ? $account.'@pms.local' : ''),
            'samaccountname' => $account,
            'userprincipalname' => $this->firstValue($entry, 'userprincipalname'),
            'department' => $this->firstValue($entry, 'department') ?: 'General',
            'dn' => (string) ($entry['dn'] ?? ''),
        ];
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
