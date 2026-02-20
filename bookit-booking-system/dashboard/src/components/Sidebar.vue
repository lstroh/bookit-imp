<template>
  <div class="h-full overflow-y-auto flex flex-col">
    <!-- Close Button (mobile only) -->
    <div class="lg:hidden flex justify-between items-center p-4 border-b border-gray-200">
      <span class="text-lg font-semibold text-gray-900">Menu</span>
      <button
        @click="emit('close')"
        class="p-2 rounded-lg hover:bg-gray-100"
        aria-label="Close navigation menu"
      >
        <svg aria-hidden="true" class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Logo (desktop only) -->
    <div class="hidden lg:block px-6 py-4 border-b border-gray-200">
      <h2 class="text-xl font-bold text-primary-600">Bookit</h2>
      <p class="text-xs text-gray-500 mt-1">Booking Dashboard</p>
    </div>

    <!-- Main Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto" aria-label="Main navigation">
      <router-link
        v-for="item in mainNavigation"
        :key="item.name"
        :to="item.path"
        class="nav-item"
        :class="{ 'active': $route.path === item.path }"
      >
        <span class="text-xl mr-3">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </router-link>
    </nav>

    <!-- Settings Section (Admin Only) -->
    <div
      v-if="props.staff.role === 'admin'"
      class="px-4 pb-4 border-t border-gray-200"
    >
      <p class="px-4 pt-4 pb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
        Settings
      </p>

      <router-link
        v-for="item in settingsNavigation"
        :key="item.name"
        :to="item.path"
        class="nav-item"
        :class="{ 'active': $route.path === item.path }"
      >
        <span class="text-xl mr-3">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  staff: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])

const mainNavigation = [
  { name: 'dashboard', path: '/', icon: '📅', label: 'Today' },
  { name: 'bookings', path: '/bookings', icon: '📋', label: 'Bookings' },
  { name: 'services', path: '/services', icon: '✂️', label: 'Services' },
  { name: 'categories', path: '/categories', icon: '🏷️', label: 'Categories' },
  { name: 'staff', path: '/staff', icon: '👥', label: 'Staff' }
]

const settingsNavigation = [
  { name: 'settings', path: '/settings', icon: '⚙️', label: 'General' },
  { name: 'emailSettings', path: '/settings/email', icon: '📧', label: 'Email Configuration' },
  { name: 'emailTemplates', path: '/settings/templates', icon: '📝', label: 'Email Templates' },
  { name: 'bulkHours', path: '/settings/bulk-hours', icon: '👥', label: 'Bulk Working Hours' }
]
</script>

<style scoped>
.nav-item {
  @apply flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors;
}

.nav-item.active {
  @apply bg-primary-50 text-primary-700;
}
</style>
