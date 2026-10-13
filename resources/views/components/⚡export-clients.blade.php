<?php

use App\Exports\ExportClient;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component {
    public function export(): BinaryFileResponse
    {
        return Excel::download(new ExportClient(), 'clients-' . now()->format('Y-m-d-His') . '.xlsx');
    }
};
?>
<flux:button wire:click="export" variant="filled" icon="arrow-down-tray">
    Export
</flux:button>
