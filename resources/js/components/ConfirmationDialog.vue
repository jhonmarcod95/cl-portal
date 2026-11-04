<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const isOpen = defineModel<boolean>('open', { default: false });

const props = withDefaults(defineProps<{
    title: string;
    description: string;
    confirmText?: string;
    loadingText?: string;
    confirmClass?: string;
    loading?: boolean;
}>(), {
    confirmText: 'Confirm',
    loadingText: 'Please wait...',
    confirmClass: 'bg-black hover:bg-gray-800',
    loading: false,
});

const emit = defineEmits<{
    confirm: [];
}>();
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="z-[100] sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription>{{ props.description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="props.loading"
                    @click="isOpen = false"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="rounded-md px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :class="props.confirmClass"
                    :disabled="props.loading"
                    @click="emit('confirm')"
                >
                    {{ props.loading ? props.loadingText : props.confirmText }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
