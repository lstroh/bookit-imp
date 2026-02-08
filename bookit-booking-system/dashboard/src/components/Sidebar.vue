<template>
  <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
    <!-- Logo -->
    <div class="px-6 py-4 border-b border-gray-200">
      <h2 class="text-xl font-bold text-primary-600">
        Bookit
      </h2>
      <p class="text-xs text-gray-500 mt-1">
        Booking Dashboard
      </p>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
      <router-link
        v-for="item in navigation"
        :key="item.name"
        :to="item.path"
        class="nav-item"
        :class="{ 'active': $route.path === item.path }"
      >
        <span class="text-xl mr-3">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </router-link>
    </nav>

    <!-- User Info -->
    <div class="px-4 py-4 border-t border-gray-200">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
          <span class="text-primary-600 font-semibold">
            {{ initials }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">
            {{ props.staff.name }}
          </p>
          <p class="text-xs text-gray-500 capitalize">
            {{ props.staff.role }}
          </p>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  staff: {
    type: Object,
    required: true
  }
})

const navigation = [
  { name: 'dashboard', path: '/', icon: '📅', label: 'Today' },
  { name: 'bookings', path: '/bookings', icon: '📋', label: 'Bookings' },
  { name: 'services', path: '/services', icon: '✂️', label: 'Services' },
  { name: 'staff', path: '/staff', icon: '👥', label: 'Staff' },
  { name: 'settings', path: '/settings', icon: '⚙️', label: 'Settings' }
]

const initials = computed(() => {
  const names = props.staff.name.split(' ')
  return names.map(n => n[0]).join('').toUpperCase()
})
</script>

<style scoped>
.nav-item {
  @apply flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors;
}

.nav-item.active {
  @apply bg-primary-50 text-primary-700;
}
</style>
