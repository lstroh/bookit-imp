<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">
        Today's Schedule
      </h2>
      <p class="text-sm text-gray-600 mt-1">
        {{ formattedDate }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading bookings...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <p class="text-sm text-red-800">{{ error }}</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="bookings.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
      <div class="text-6xl mb-4">📅</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">
        No bookings today
      </h3>
      <p class="text-sm text-gray-600">
        You have a clear schedule for today.
      </p>
    </div>

    <!-- Bookings List -->
    <div v-else class="space-y-4">
      <div
        v-for="booking in bookings"
        :key="booking.id"
        class="bg-white rounded-lg shadow p-6"
      >
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-lg font-semibold text-gray-900">
                {{ booking.start_time }}
              </span>
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="statusClass(booking.status)"
              >
                {{ booking.status }}
              </span>
            </div>

            <p class="text-sm text-gray-600 mt-1">
              {{ booking.service_name }}
            </p>

            <div class="mt-2 text-sm text-gray-700">
              <p><strong>Customer:</strong> {{ booking.customer_name }}</p>
              <p><strong>Staff:</strong> {{ booking.staff_name }}</p>
            </div>
          </div>

          <div class="flex gap-2">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
            >
              View Details
            </button>
            <button
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
            >
              Mark Complete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const loading = ref(true)
const error = ref(null)
const bookings = ref([])

const formattedDate = computed(() => {
  return new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

const statusClass = (status) => {
  const classes = {
    'confirmed': 'bg-green-100 text-green-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'pending_payment': 'bg-orange-100 text-orange-800',
    'completed': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-red-100 text-red-800',
    'no_show': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

onMounted(async () => {
  try {
    // This endpoint doesn't exist yet - will create in Task 3.
    // For now, just show empty state.
    bookings.value = []

    // Uncomment when endpoint is ready:
    // const response = await api.get('/bookings/today')
    // bookings.value = response.data

  } catch (err) {
    error.value = err.message || 'Failed to load bookings'
  } finally {
    loading.value = false
  }
})
</script>
