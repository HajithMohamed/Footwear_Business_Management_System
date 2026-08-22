<!-- Reusable Alpine.js Confirmation Dialog Component -->
<!-- 
  Usage:
  Add this to a form instead of onsubmit="return confirm('...')"
  x-data="{}" @submit.prevent="$dispatch('confirm-action', { 
    title: 'Delete Customer?', 
    message: 'Are you sure you want to delete this customer?', 
    confirmText: 'Delete', 
    type: 'danger', 
    onConfirm: () => $el.submit() 
  })"
-->

<div x-data="{
    open: false,
    title: 'Confirm Action',
    message: 'Are you sure you want to proceed?',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    type: 'primary', /* 'primary', 'danger', 'success' */
    onConfirm: null,

    handleConfirm() {
        if (typeof this.onConfirm === 'function') {
            this.onConfirm();
        }
        this.open = false;
    }
}" 
@confirm-action.window="
    title = $event.detail.title || 'Confirm Action';
    message = $event.detail.message || 'Are you sure you want to proceed?';
    confirmText = $event.detail.confirmText || 'Confirm';
    cancelText = $event.detail.cancelText || 'Cancel';
    type = $event.detail.type || 'primary';
    onConfirm = $event.detail.onConfirm;
    open = true;
">
    
    <div x-show="open" style="display: none;" class="confirm-overlay">
        <!-- Backdrop -->
        <div class="absolute inset-0" @click="open = false" x-show="open" x-transition.opacity></div>

        <!-- Dialog -->
        <div class="confirm-dialog relative" 
             x-show="open" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            
            <h3 class="confirm-dialog-title" x-text="title"></h3>
            <p class="confirm-dialog-message" x-text="message"></p>
            
            <div class="confirm-dialog-actions">
                <button @click="open = false" type="button" class="btn btn-outline text-slate-600 font-bold" x-text="cancelText"></button>
                <button @click="handleConfirm()" type="button" 
                        class="btn font-bold text-white shadow-sm"
                        :class="{
                            'bg-brand-600 hover:bg-brand-700': type === 'primary',
                            'bg-red-600 hover:bg-red-700': type === 'danger',
                            'bg-green-600 hover:bg-green-700': type === 'success'
                        }"
                        x-text="confirmText"></button>
            </div>
        </div>
    </div>
</div>
