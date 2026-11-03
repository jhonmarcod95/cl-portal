<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/goc-logo.png"
        alt="Company Logo"
        :class="className"
        style="
            width: auto;
            height: auto;
            max-width: 2000px;
            max-height: 100%;
            object-fit: contain;
            background-color: white;
        "
        v-bind="$attrs"
    />
</template>