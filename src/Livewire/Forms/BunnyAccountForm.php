<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Forms;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Livewire\Form;

class BunnyAccountForm extends Form
{
    public ?BunnyAccount $account = null;

    public string $name = '';

    public string $api_key = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // On edit an empty key means "keep the current one".
            'api_key' => [$this->account === null ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function setAccount(BunnyAccount $account): void
    {
        $this->account = $account;
        $this->name = $account->name;
        $this->api_key = '';
    }

    public function save(): BunnyAccount
    {
        $this->validate();

        if ($this->account === null) {
            $account = BunnyAccount::query()->create([
                'name' => $this->name,
                'api_key' => $this->api_key,
            ]);
        } else {
            $account = $this->account;
            $account->update(array_filter([
                'name' => $this->name,
                'api_key' => $this->api_key !== '' ? $this->api_key : null,
            ], fn (mixed $value): bool => $value !== null));
        }

        $this->reset();

        return $account;
    }
}
