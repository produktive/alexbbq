<?php

use App\Models\Cook;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component {
    public Cook $cook;

    public function mount(Cook $cook): void
    {
        $this->cook = $cook;
    }
};
?>

<flux:container>
    <flux:heading level="1" size="xl">{{ $cook->title }}</flux:heading>
    <flux:text>{{ Carbon::parse($cook->created_at)->since() }}</flux:text>

    <div class="my-6">
        <livewire:app.livewire.cookchart :cook="$cook" />
    </div>

    <div class="max-w-3xl my-6">
        {!! $cook->description !!}
    </div>
</flux:container>
