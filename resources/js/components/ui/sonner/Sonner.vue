<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
  XIcon,
} from "@lucide/vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import "vue-sonner/style.css"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
      '--success-bg': '#16a34a',
      '--success-text': '#ffffff',
      '--success-border': '#15803d',
      '--error-bg': '#dc2626',
      '--error-text': '#ffffff',
      '--error-border': '#b91c1c',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4 text-green-500" />
    </template>

    <template #info-icon>
      <InfoIcon class="size-4 text-blue-500" />
    </template>

    <template #warning-icon>
      <TriangleAlertIcon class="size-4 text-yellow-500" />
    </template>

    <template #error-icon>
      <OctagonXIcon class="size-4 text-red-500" />
    </template>

    <template #loading-icon>
      <Loader2Icon class="size-4 animate-spin text-primary" />
    </template>

    <template #close-icon>
      <XIcon class="size-4 text-muted-foreground" />
    </template>
  </Sonner>
</template>