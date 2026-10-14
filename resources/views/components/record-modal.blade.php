@props(['title', 'button' => null, 'reopen' => false])
<div x-data x-init="{{ $reopen ? '$nextTick(() => $refs.dialog.showModal())' : '' }}">
    <button type="button" class="customer-new-button" @click="$refs.dialog.showModal()">{{ $button ?? '+ '.$title }}</button>
    <dialog x-ref="dialog" class="customer-create-dialog" aria-label="{{ $title }}">
        <header class="customer-dialog-header"><h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2><button type="button" class="customer-dialog-close" @click="$refs.dialog.close()" aria-label="Cerrar {{ $title }}">×</button></header>
        <div class="p-5">{{ $slot }}</div>
    </dialog>
</div>
