<div x-show="! selection.active" class="flex flex-col">
    <button
        type="button"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="mountPointAction('addNote')"
    >
        <flux:icon.pencil-square variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        <span x-text="menu.pointNote ? 'Edit note' : 'Add note'"></span>
    </button>

    <div class="my-1 border-t border-zinc-200 dark:border-zinc-700"></div>

    <button
        type="button"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="mountPointAction('deletePoint')"
    >
        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        Delete this point
    </button>
    <button
        type="button"
        x-show="menu.canDeleteBefore"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="mountPointAction('deleteBefore')"
    >
        <flux:icon.chevron-double-left variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        Delete all before this point
    </button>
    <button
        type="button"
        x-show="menu.canDeleteAfter"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="mountPointAction('deleteAfter')"
    >
        <flux:icon.chevron-double-right variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        Delete all after this point
    </button>
</div>

<div x-show="selection.active" class="flex flex-col">
    <button
        type="button"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="removeSelected()"
    >
        <flux:icon.trash variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        <span x-text="`Delete ${selection.ids.length} selected points`"></span>
    </button>
    <button
        type="button"
        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-zinc-100 sm:px-3 sm:py-1.5 dark:hover:bg-zinc-800"
        @click="closeMenu(); clearSelection()"
    >
        <flux:icon.x-mark variant="mini" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
        Clear selection
    </button>
</div>
