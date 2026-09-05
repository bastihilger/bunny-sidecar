<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire;

use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Livewire\Forms\ZoneGrantsForm;
use Livewire\Component;

/**
 * Base for pages embedding the zone grants editor partial
 * (resources/views/partials/zone-grants-editor.blade.php).
 */
abstract class ZoneGrantsPage extends Component
{
    use ResolvesCurrentUser;

    public ZoneGrantsForm $grantsForm;

    public function addGrantRow(): void
    {
        $this->grantsForm->addRow();
    }

    public function removeGrantRow(int $index): void
    {
        $this->grantsForm->removeRow($index);
    }

    /**
     * All selectable zones across accounts, keyed "{accountId}:{domain}".
     *
     * @return array<string, string>
     */
    protected function zoneOptions(): array
    {
        try {
            return app(ZoneDirectory::class)
                ->all()
                ->mapWithKeys(fn (array $row): array => [
                    "{$row['account']->id}:".mb_strtolower($row['zone']->domain) => "{$row['zone']->domain} ({$row['account']->name})",
                ])
                ->all();
        } catch (BunnyApiException) {
            return [];
        }
    }
}
