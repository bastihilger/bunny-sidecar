<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Forms;

use BunnySidecar\BunnySidecar\Dtos\ZoneGrantPermissions;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Editable zone grant rows shared by the invite and user edit pages. A row's
 * zone is addressed as "{bunny_account_id}:{domain}"; per scope (keys
 * "{scope}_mode" / "{scope}_types") the mode is either none, all, or a
 * selected list of record types the member may add there.
 */
class ZoneGrantsForm extends Form
{
    public const string MODE_NONE = 'none';

    public const string MODE_ALL = 'all';

    public const string MODE_SELECTED = 'selected';

    /**
     * @var list<array<string, string|list<int>>>
     */
    public array $rows = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modes = Rule::in([self::MODE_NONE, self::MODE_ALL, self::MODE_SELECTED]);
        $types = Rule::in(array_column(DnsEntryType::cases(), 'value'));

        $rules = [
            'rows' => ['array'],
            'rows.*.zone' => ['required', 'string', 'regex:/^\d+:.+$/', 'distinct'],
        ];

        foreach (DnsRecordScope::cases() as $scope) {
            $rules["rows.*.{$scope->value}_mode"] = ['required', $modes];
            $rules["rows.*.{$scope->value}_types"] = ['array'];
            $rules["rows.*.{$scope->value}_types.*"] = ['integer', $types];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'rows.*.zone' => __('zone'),
        ];
    }

    public function addRow(): void
    {
        $row = ['zone' => ''];

        foreach (DnsRecordScope::cases() as $scope) {
            $row["{$scope->value}_mode"] = self::MODE_NONE;
            $row["{$scope->value}_types"] = [];
        }

        $this->rows[] = $row;
    }

    public function removeRow(int $index): void
    {
        array_splice($this->rows, $index, 1);
    }

    /**
     * @param  Collection<array-key, DnsZoneGrant>  $grants
     */
    public function fillFromGrants(Collection $grants): void
    {
        $this->rows = array_values($grants->map(function (DnsZoneGrant $grant): array {
            $row = ['zone' => "{$grant->bunny_account_id}:{$grant->zone_domain}"];

            foreach (DnsRecordScope::cases() as $scope) {
                $row["{$scope->value}_mode"] = match (true) {
                    $grant->permissions->allowsAll($scope) => self::MODE_ALL,
                    $grant->permissions->allowsAny($scope) => self::MODE_SELECTED,
                    default => self::MODE_NONE,
                };
                $row["{$scope->value}_types"] = $grant->permissions->allowsAll($scope)
                    ? []
                    : array_map(fn (DnsEntryType $type): int => $type->value, $grant->permissions->typesFor($scope));
            }

            return $row;
        })->all());
    }

    /**
     * The validated rows as grant attributes ready for persistence.
     *
     * @return list<array{bunny_account_id: int, zone_domain: string, permissions: array<string, mixed>}>
     */
    public function toGrantData(): array
    {
        $grants = [];

        foreach ($this->rows as $row) {
            $zone = $row['zone'] ?? '';

            if (! is_string($zone) || $zone === '') {
                continue;
            }

            [$accountId, $domain] = explode(':', $zone, 2);
            $key = (int) $accountId.':'.mb_strtolower($domain);

            if (isset($grants[$key])) {
                continue;
            }

            $permissions = [];

            foreach (DnsRecordScope::cases() as $scope) {
                $types = $row["{$scope->value}_types"] ?? [];

                $permissions[$scope->value] = match ($row["{$scope->value}_mode"] ?? self::MODE_NONE) {
                    self::MODE_ALL => ZoneGrantPermissions::ALL_TYPES,
                    self::MODE_SELECTED => array_map(intval(...), is_array($types) ? $types : []),
                    default => [],
                };
            }

            $grants[$key] = [
                'bunny_account_id' => (int) $accountId,
                'zone_domain' => mb_strtolower($domain),
                'permissions' => $permissions,
            ];
        }

        return array_values($grants);
    }
}
