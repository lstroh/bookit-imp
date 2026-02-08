---
name: vue-frontend
description: Vue 3 Composition API conventions and best practices for the BookIt frontend. Use when creating or editing Vue components, working with .vue files, setting up Vue project structure, or implementing reactive state, routing, or API integration with Vue.
---

# Vue 3 Frontend Development

## Context7 Integration

When working with Vue ecosystem libraries, use Context7 to fetch up-to-date documentation before writing code:

1. **Resolve the library** using `resolve-library-id` with the library name (e.g., "vue", "pinia", "vue-router").
2. **Query the docs** using `query-docs` with a specific question about the API or pattern you need.

Use Context7 when:
- Implementing a Vue API you're unsure about (e.g., `defineModel`, `Suspense`, `useTemplateRef`)
- Working with Vue ecosystem packages (Pinia, Vue Router, VueUse)
- Checking for the latest recommended patterns or recently introduced features

Do not use Context7 for general coding logic or project-specific conventions already covered in this skill.

## Core Conventions

- Use **Vue 3** with the **Composition API** exclusively. Do not use the Options API.
- Use `<script setup>` syntax for all single-file components.
- Use **TypeScript** in all Vue components and composables.
- Prefer `ref()` for primitives and `reactive()` for objects when appropriate.
- Use `computed()` for derived state. Avoid computing values inside templates.
- Use `defineProps` and `defineEmits` with TypeScript type-based declarations.

## Component Structure

Follow this ordering within single-file components:

```vue
<script setup lang="ts">
// 1. Imports
// 2. Props and emits
// 3. Reactive state (ref, reactive)
// 4. Computed properties
// 5. Watchers
// 6. Lifecycle hooks
// 7. Methods / functions
</script>

<template>
  <!-- Single root element preferred -->
</template>

<style scoped>
/* Scoped styles by default */
</style>
```

## Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Components | PascalCase | `BookingCalendar.vue` |
| Composables | camelCase with `use` prefix | `useBookings.ts` |
| Props | camelCase | `bookingDate` |
| Events | camelCase | `@updateBooking` |
| Directories | kebab-case | `booking-views/` |

## State Management

- Use **Pinia** for global/shared state.
- Define stores using the **setup store** syntax (Composition API style).
- Keep component-local state in `ref()` / `reactive()` -- only lift to Pinia when shared across components.

```typescript
// stores/booking.ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useBookingStore = defineStore('booking', () => {
  const bookings = ref<Booking[]>([])
  const upcoming = computed(() =>
    bookings.value.filter(b => new Date(b.date) > new Date())
  )

  async function fetchBookings() {
    // API call
  }

  return { bookings, upcoming, fetchBookings }
})
```

## API Integration

- Use composables (e.g., `useApi`, `useBookings`) to encapsulate API calls.
- Communicate with the WordPress/PHP backend via the WordPress REST API or admin-ajax.php.
- Handle loading and error states explicitly in composables.

```typescript
// composables/useApi.ts
import { ref } from 'vue'

export function useApi<T>(fetchFn: () => Promise<T>) {
  const data = ref<T | null>(null)
  const error = ref<string | null>(null)
  const loading = ref(false)

  async function execute() {
    loading.value = true
    error.value = null
    try {
      data.value = await fetchFn()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Unknown error'
    } finally {
      loading.value = false
    }
  }

  return { data, error, loading, execute }
}
```

## WordPress Integration Notes

- The Vue app will be enqueued via `wp_enqueue_script()` from the WordPress plugin.
- Use `wp_localize_script()` or `wp_add_inline_script()` to pass PHP data (nonces, REST URLs, user info) to the Vue app.
- Always include the WordPress nonce in REST API requests for authentication.

## Key Principles

- Keep components small and focused -- extract logic into composables.
- Prefer `v-if` over `v-show` unless toggling frequently.
- Use `<Suspense>` for async component loading when appropriate.
- Always provide key attributes on `v-for` lists.
- Avoid direct DOM manipulation -- use template refs when needed.
