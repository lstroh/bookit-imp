<template>
  <div class="min-h-screen bg-gray-100">
    <div class="flex h-screen overflow-hidden">
      <!-- Sidebar -->
      <Sidebar
        :staff="staff"
        @logout="handleLogout"
      />

      <!-- Main Content -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Header -->
        <header class="bg-white shadow-sm z-10">
          <div class="px-6 py-4">
            <div class="flex items-center justify-between">
              <h1 class="text-2xl font-semibold text-gray-900">
                {{ pageTitle }}
              </h1>

              <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">
                  {{ staff.name }}
                </span>
                <button
                  @click="handleLogout"
                  class="text-sm text-gray-600 hover:text-gray-900"
                >
                  Logout
                </button>
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
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'

// Get staff data from window (injected by PHP).
const staff = window.BOOKIT_DASHBOARD.staff

// Get current page title from route.
const route = useRoute()
const pageTitle = computed(() => route.meta.title || 'Dashboard')

// Handle logout.
const handleLogout = () => {
  window.location.href = window.BOOKIT_DASHBOARD.logoutUrl
}
</script>
