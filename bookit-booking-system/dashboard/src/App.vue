<template>
  <div class="min-h-screen bg-gray-100">
    <div class="flex h-screen overflow-hidden">
      <!-- Sidebar -->
      <Sidebar :staff="staff" />

      <!-- Main Content -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Header -->
        <header class="bg-white shadow-sm z-10">
          <div class="px-6 py-4">
            <div class="flex items-center justify-between">
              <h1 class="text-2xl font-semibold text-gray-900">
                {{ pageTitle }}
              </h1>

              <!-- User Dropdown -->
              <div ref="userDropdownRef" class="relative">
                <button
                  @click.stop="showUserMenu = !showUserMenu"
                  class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                  <div
                    class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                    :style="{ backgroundColor: getUserColor(staff.name) }"
                  >
                    {{ getUserInitials(staff.name) }}
                  </div>
                  <span class="hidden sm:inline">{{ staff.name }}</span>
                  <svg
                    class="w-4 h-4 transition-transform"
                    :class="{ 'rotate-180': showUserMenu }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <!-- Dropdown Menu -->
                <div
                  v-show="showUserMenu"
                  @click.stop
                  class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
                >
                  <div class="px-4 py-2 border-b border-gray-100">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ staff.name }}</p>
                    <p class="text-xs text-gray-500 capitalize">{{ staff.role }}</p>
                  </div>

                  <router-link
                    to="/profile"
                    @click="showUserMenu = false"
                    class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  >
                    <span>👤</span>
                    <span>My Profile</span>
                  </router-link>

                  <div class="border-t border-gray-100 my-1"></div>

                  <button
                    @click="handleLogout"
                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                  >
                    <span>🚪</span>
                    <span>Logout</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
          <router-view />
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import { useApi } from './composables/useApi'

const api = useApi()
const staff = window.BOOKIT_DASHBOARD.staff

const route = useRoute()
const pageTitle = computed(() => route.meta.title || 'Dashboard')

const showUserMenu = ref(false)
const userDropdownRef = ref(null)

const onDocumentClick = (event) => {
  if (showUserMenu.value && userDropdownRef.value && !userDropdownRef.value.contains(event.target)) {
    showUserMenu.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})

const getUserInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) return names[0].substring(0, 2).toUpperCase()
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

const getUserColor = (name) => {
  const colors = [
    '#3B82F6', '#8B5CF6', '#EC4899', '#10B981',
    '#F59E0B', '#EF4444', '#6366F1', '#14B8A6'
  ]
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

const handleLogout = async () => {
  showUserMenu.value = false
  try {
    await api.post('logout')
  } catch {
    // Proceed with redirect even if API call fails.
  }
  window.location.href = window.BOOKIT_DASHBOARD.logoutUrl
}
</script>
