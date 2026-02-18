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

    <!-- Main Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
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
  </aside>
</template>

<script setup>
const props = defineProps({
  staff: {
    type: Object,
    required: true
  }
})

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
  { name: 'emailTemplates', path: '/settings/templates', icon: '📝', label: 'Email Templates' }
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
