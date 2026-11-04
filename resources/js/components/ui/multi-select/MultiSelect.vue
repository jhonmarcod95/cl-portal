<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type OptionValue = string | number;

const props = defineProps<{
    modelValue: OptionValue[];
    options: Array<{ label: string; value: OptionValue }>;
    name: string;
    label: string;
    placeholder: string;
    searchable?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: OptionValue[]];
}>();

const root = ref<HTMLElement | null>(null);
const control = ref<HTMLElement | null>(null);
const dropdown = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const dropdownStyle = ref<Record<string, string>>({});
const searchQuery = ref('');
const filteredOptions = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();
    return query
        ? props.options.filter((option) => option.label.toLocaleLowerCase().includes(query))
        : props.options;
});

function handleOutsideClick(event: PointerEvent) {
    const target = event.target;
    if (!(target instanceof Node)) return;

    if (!root.value?.contains(target) && !dropdown.value?.contains(target)) {
        isOpen.value = false;
    }
}

function positionDropdown() {
    const bounds = control.value?.getBoundingClientRect();
    if (!bounds) return;

    dropdownStyle.value = {
        left: `${bounds.left}px`,
        top: `${bounds.bottom + 4}px`,
        width: `${bounds.width}px`,
    };
}

watch(isOpen, async (open) => {
    if (!open) {
        window.removeEventListener('resize', positionDropdown);
        window.removeEventListener('scroll', positionDropdown, true);
        searchQuery.value = '';
        return;
    }

    await nextTick();
    positionDropdown();
    if (props.searchable) searchInput.value?.focus();
    window.addEventListener('resize', positionDropdown);
    window.addEventListener('scroll', positionDropdown, true);
});

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsideClick);
    window.removeEventListener('resize', positionDropdown);
    window.removeEventListener('scroll', positionDropdown, true);
});

function isSelected(value: OptionValue) {
    return props.modelValue.includes(value);
}

function toggleOption(value: OptionValue, checked: boolean) {
    const values = checked
        ? [...props.modelValue, value]
        : props.modelValue.filter((selected) => selected !== value);

    emit('update:modelValue', values);
}

function removeOption(value: OptionValue) {
    emit('update:modelValue', props.modelValue.filter((selected) => selected !== value));
}

function closeOnEscape() {
    isOpen.value = false;
    trigger.value?.focus();
}
</script>

<template>
    <div ref="root" class="relative">
        <div
            ref="control"
            class="flex min-h-10 items-center gap-2 rounded-md border border-[#d5e0eb] bg-white px-2 py-1.5 shadow-sm transition focus-within:border-[#4e70e9] focus-within:ring-2 focus-within:ring-[#4e70e9]/15"
        >
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                <span
                    v-for="option in options.filter((item) => isSelected(item.value))"
                    :key="option.value"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-900 px-2.5 py-1 text-xs font-medium text-white"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <button
                        type="button"
                        :aria-label="`Remove ${option.label}`"
                        class="shrink-0 rounded-full text-white/90 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                        @click="removeOption(option.value)"
                    >
                        ×
                    </button>
                    <input type="hidden" :name="name" :value="option.value">
                </span>
                <input
                    v-if="searchable"
                    ref="searchInput"
                    v-model="searchQuery"
                    type="search"
                    :aria-label="`Search ${label.toLocaleLowerCase()}`"
                    :placeholder="`Search ${label.toLocaleLowerCase()}...`"
                    class="min-w-[8rem] flex-1 border-0 bg-transparent px-1 py-1 text-sm text-[#435570] outline-none placeholder:text-[#9aa9bc] focus:ring-0"
                    @focus="isOpen = true"
                    @keydown.esc.prevent="closeOnEscape"
                >
                <span v-else-if="modelValue.length === 0" class="px-1 text-sm text-[#9aa9bc]">
                    {{ placeholder }}
                </span>
            </div>

            <button
                ref="trigger"
                type="button"
                :aria-label="label"
                :aria-expanded="isOpen"
                aria-haspopup="true"
                class="flex size-7 shrink-0 items-center justify-center rounded text-[#8292a8] transition hover:bg-slate-50 hover:text-[#51627d] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4e70e9]/30"
                @click="isOpen = !isOpen"
                @keydown.esc.prevent="closeOnEscape"
            >
                <svg
                    aria-hidden="true"
                    class="size-4 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdown"
                role="group"
                :aria-label="label"
                :style="dropdownStyle"
                class="fixed z-[100] max-h-60 overflow-y-auto rounded-md border border-[#d5e0eb] bg-white p-1.5 shadow-lg"
            >
                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    :aria-pressed="isSelected(option.value)"
                    class="flex w-full cursor-pointer items-center rounded px-2.5 py-2 text-left text-sm transition"
                    :class="isSelected(option.value) ? 'bg-slate-700 text-white' : 'text-black hover:bg-gray-100'"
                    @click="toggleOption(option.value, !isSelected(option.value))"
                >
                    <span>{{ option.label }}</span>
                </button>
                <p v-if="filteredOptions.length === 0" class="px-2.5 py-2 text-sm text-[#8a9ab0]">
                    {{ options.length === 0 ? 'No options available.' : 'No matching options.' }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
