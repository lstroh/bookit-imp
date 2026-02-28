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
      <a
        v-for="item in extensionNavigation"
        :key="`extension-${item.route}`"
        :href="item.route"
        class="nav-item"
      >
        <span class="text-xl mr-3">{{ resolveNavIcon(item.icon) }}</span>
        <span>{{ item.label }}</span>
      </a>
    </nav>

    <!-- Admin Sections -->
    <div
      v-if="props.staff.role === 'admin'"
      class="pb-4"
    >
      <!-- Reports Section (Admin Only) -->
      <div class="border-t border-gray-200">
        <button
          @click="toggleReports"
          class="w-full flex items-center justify-between px-4 pt-4 pb-2 text-left"
          :aria-expanded="reportsOpen"
          aria-controls="reports-nav"
        >
          <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</span>
          <svg
            class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200"
            :class="reportsOpen ? 'rotate-90' : 'rotate-0'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="section-slide">
          <div
            id="reports-nav"
            v-show="reportsOpen"
            class="px-4 pb-2 space-y-1"
          >
            <router-link
              v-for="item in reportsNavigation"
              :key="item.name"
              :to="item.path"
              class="nav-item"
              :class="{ 'active': $route.path === item.path || $route.path.startsWith(item.path + '/') }"
            >
              <span class="text-xl mr-3">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- Settings Section (Admin Only) -->
      <div class="border-t border-gray-200 pb-4">
        <button
          @click="toggleSettings"
          class="w-full flex items-center justify-between px-4 pt-4 pb-2 text-left"
          :aria-expanded="settingsOpen"
          aria-controls="settings-nav"
        >
          <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Settings</span>
          <svg
            class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200"
            :class="settingsOpen ? 'rotate-90' : 'rotate-0'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <transition name="section-slide">
          <div
            id="settings-nav"
            v-show="settingsOpen"
            class="px-4 pb-2 space-y-1"
          >
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
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'

const props = defineProps({
  staff: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])
const route = useRoute()
const api = useApi()

const mainNavigation = [
  { name: 'dashboard', path: '/', icon: '📅', label: 'Today' },
  { name: 'mySchedule', path: '/my-schedule', icon: '🗓️', label: 'My Schedule' },
  { name: 'myAvailability', path: '/my-availability', icon: '🚫', label: 'My Availability' },
  { name: 'bookings', path: '/bookings', icon: '📋', label: 'Bookings' },
  { name: 'services', path: '/services', icon: '✂️', label: 'Services' },
  { name: 'categories', path: '/categories', icon: '🏷️', label: 'Categories' },
  { name: 'staff', path: '/staff', icon: '👥', label: 'Staff' }
]

const settingsNavigation = [
  { name: 'settings', path: '/settings', icon: '⚙️', label: 'General' },
  { name: 'emailSettings', path: '/settings/email', icon: '📧', label: 'Email Configuration' },
  { name: 'emailTemplates', path: '/settings/templates', icon: '📝', label: 'Email Templates' },
  { name: 'bulkHours', path: '/settings/bulk-hours', icon: '👥', label: 'Bulk Working Hours' },
  { name: 'settingsExtensions', path: '/settings/extensions', icon: '🧩', label: 'Extensions' }
]

const reportsNavigation = [
  { name: 'reportsOverview', path: '/reports', icon: '📊', label: 'Overview' },
  { name: 'revenueReport', path: '/reports/revenue', icon: '💷', label: 'Revenue' },
  { name: 'bookingAnalytics', path: '/reports/bookings', icon: '📈', label: 'Bookings' },
  { name: 'staffPerformance', path: '/reports/staff', icon: '👥', label: 'Staff Performance' },
  { name: 'customers', path: '/customers', icon: '👤', label: 'Customers' }
]

const extensionNavigation = ref([])

// Collapsible section state — default collapsed
const reportsOpen = ref(false)
const settingsOpen = ref(false)

onMounted(() => {
  // Restore from localStorage
  const storedReports = localStorage.getItem('bookit_sidebar_reports_open')
  const storedSettings = localStorage.getItem('bookit_sidebar_settings_open')

  reportsOpen.value = storedReports === 'true'
  settingsOpen.value = storedSettings === 'true'

  // Auto-expand if current route is inside a collapsed section
  const inReports = reportsNavigation.some(
    item => route.path === item.path || route.path.startsWith(item.path + '/')
  )
  const inSettings = settingsNavigation.some(
    item => route.path === item.path
  )

  if (inReports) reportsOpen.value = true
  if (inSettings) settingsOpen.value = true

  loadExtensionNavigation()
})

function toggleReports() {
  reportsOpen.value = !reportsOpen.value
  localStorage.setItem('bookit_sidebar_reports_open', String(reportsOpen.value))
}

function toggleSettings() {
  settingsOpen.value = !settingsOpen.value
  localStorage.setItem('bookit_sidebar_settings_open', String(settingsOpen.value))
}

watch(reportsOpen, value => {
  localStorage.setItem('bookit_sidebar_reports_open', String(value))
})

watch(settingsOpen, value => {
  localStorage.setItem('bookit_sidebar_settings_open', String(value))
})

async function loadExtensionNavigation() {
  try {
    const response = await api.get(`${window.BOOKIT_DASHBOARD.restBase}extensions`)
    const navItems = Array.isArray(response.data?.nav_items) ? response.data.nav_items : []

    extensionNavigation.value = navItems.filter(item => hasCapability(item.capability))
  } catch {
    extensionNavigation.value = []
  }
}

function hasCapability(capability) {
  if (!capability || capability === 'bookit_manage_all') {
    return props.staff.role === 'admin'
  }

  return props.staff.role === 'admin'
}

function resolveNavIcon(icon) {
  const iconMap = {
    'calendar-repeat': '🔁',
    calendar: '📅',
    booking: '📋',
    staff: '👥',
    settings: '⚙️'
  }

  return iconMap[icon] || '🧩'
}
</script>

<style scoped>
.nav-item {
  @apply flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors;
}

.nav-item.active {
  @apply bg-primary-50 text-primary-700;
}

.section-slide-enter-active,
.section-slide-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}

.section-slide-enter-from,
.section-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
