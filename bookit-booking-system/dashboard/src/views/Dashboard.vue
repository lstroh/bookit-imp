<template>
  <div>
    <!-- Header -->
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
      <div class="flex items-start">
        <span class="text-2xl mr-3">&#x26A0;&#xFE0F;</span>
        <div>
          <h3 class="text-sm font-medium text-red-800">Error Loading Bookings</h3>
          <p class="text-sm text-red-700 mt-1">{{ error }}</p>
          <button
            @click="loadBookings"
            class="mt-2 text-sm text-red-600 hover:text-red-800 underline"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="bookings.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
      <div class="text-6xl mb-4">&#x1F4C5;</div>
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
        class="bg-white rounded-lg shadow hover:shadow-md transition-shadow"
        :class="{ 'ring-2 ring-orange-400': booking.is_starting_soon }"
      >
        <div class="p-6">
          <!-- Header Row -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <!-- Time and Status -->
              <div class="flex items-center gap-3 mb-2">
                <span class="text-xl font-semibold text-gray-900">
                  {{ booking.start_time }}
                </span>
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
                <span
                  v-if="booking.is_starting_soon && booking.status !== 'completed' && booking.status !== 'cancelled'"
                  class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full animate-pulse"
                >
                  Starting Soon
                </span>
                <span
                  v-if="booking.has_passed && booking.status !== 'completed' && booking.status !== 'cancelled'"
                  class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full"
                >
                  Overdue
                </span>
              </div>

              <!-- Service Name -->
              <h3 class="text-base font-medium text-gray-900 mb-3">
                {{ booking.service_name }}
              </h3>

              <!-- Details Grid -->
              <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div>
                  <span class="text-gray-500">Customer:</span>
                  <span class="ml-2 text-gray-900 font-medium">{{ booking.customer_name }}</span>
                </div>
                <div>
                  <span class="text-gray-500">Staff:</span>
                  <span class="ml-2 text-gray-900">{{ booking.staff_name }}</span>
                </div>
                <div>
                  <span class="text-gray-500">Duration:</span>
                  <span class="ml-2 text-gray-900">{{ booking.duration }} min</span>
                </div>
                <div>
                  <span class="text-gray-500">Payment:</span>
                  <span class="ml-2 text-gray-900">{{ formatPaymentStatus(booking) }}</span>
                </div>
              </div>

              <!-- Special Requests -->
              <div v-if="booking.special_requests" class="mt-3 text-sm">
                <span class="text-gray-500">Note:</span>
                <span class="ml-2 text-gray-700 italic">{{ booking.special_requests }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-2 ml-4">
              <button
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors whitespace-nowrap"
                @click="viewDetails(booking)"
              >
                View Details
              </button>
              <button
                v-if="booking.status !== 'completed' && booking.status !== 'cancelled'"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors whitespace-nowrap"
                :disabled="markingComplete === booking.id"
                @click="markComplete(booking)"
              >
                {{ markingComplete === booking.id ? 'Updating...' : 'Mark Complete' }}
              </button>
              <span
                v-else-if="booking.status === 'completed'"
                class="px-4 py-2 text-sm font-medium text-green-700 bg-green-50 rounded-lg text-center"
              >
                Completed
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Booking View/Edit Modal -->
  <BookingViewModal
    v-if="showViewModal && selectedBookingId"
    :booking-id="selectedBookingId"
    @close="closeViewModal"
    @updated="handleBookingUpdated"
    @cancelled="handleBookingCancelled"
  />
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useApi } from '../composables/useApi'
import BookingViewModal from '../components/BookingViewModal.vue'

const api = useApi()

const loading = ref(true)
const error = ref(null)
const bookings = ref([])
const markingComplete = ref(null)
const showViewModal = ref(false)
const selectedBookingId = ref(null)

const formattedDate = computed(() => {
  return new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

const getStatusClass = (status) => {
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

const formatStatus = (status) => {
  const labels = {
    'confirmed': 'Confirmed',
    'pending': 'Pending',
    'pending_payment': 'Pending Payment',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'no_show': 'No Show'
  }
  return labels[status] || status
}

const formatPaymentStatus = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  if (paid > total && total > 0) {
    // Overpayment (tip included).
    return `\u2713 \u00A3${paid.toFixed(2)} paid (incl. tip)`
  }
  if (paid >= total && total > 0) {
    // Fully paid (exact amount).
    return `\u2713 \u00A3${paid.toFixed(2)} paid in full`
  }
  if (paid > 0) {
    // Partially paid.
    return `\u00A3${paid.toFixed(2)} paid, \u00A3${(total - paid).toFixed(2)} due`
  }
  if (booking.payment_method === 'pay_on_arrival') {
    return `\u00A3${total.toFixed(2)} (Pay on Arrival)`
  }
  return `\u00A3${total.toFixed(2)} (Unpaid)`
}

const loadBookings = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await api.get('/bookings/today')

    if (response.data.success) {
      bookings.value = response.data.bookings
    } else {
      throw new Error(response.data.message || 'Failed to load bookings')
    }
  } catch (err) {
    console.error('Error loading bookings:', err)
    error.value = err.message || 'Failed to load bookings. Please try again.'
  } finally {
    loading.value = false
  }
}

const viewDetails = (booking) => {
  selectedBookingId.value = booking.id
  showViewModal.value = true
}

const closeViewModal = () => {
  showViewModal.value = false
  selectedBookingId.value = null
}

const handleBookingUpdated = (updatedBooking) => {
  // Refresh today's schedule after booking update.
  loadBookings()
  showViewModal.value = false
  selectedBookingId.value = null
}

const handleBookingCancelled = (bookingId) => {
  // Refresh today's schedule after cancellation.
  loadBookings()
  showViewModal.value = false
  selectedBookingId.value = null
}

const markComplete = async (booking) => {
  if (markingComplete.value) return

  // Simple confirmation for now.
  const confirmed = confirm(
    'Mark booking as complete?\n\n' +
    `Customer: ${booking.customer_name}\n` +
    `Service: ${booking.service_name}\n` +
    `Time: ${booking.start_time}\n\n` +
    'Note: A full completion interface with notes will be available in Task 6.'
  )

  if (!confirmed) return

  markingComplete.value = booking.id

  try {
    const response = await api.post(`/bookings/${booking.id}/complete`)

    if (response.data.success) {
      // Update local state.
      const index = bookings.value.findIndex(b => b.id === booking.id)
      if (index !== -1) {
        bookings.value[index].status = 'completed'
      }

      alert('Booking marked as complete!')
    } else {
      throw new Error(response.data.message || 'Failed to mark complete')
    }
  } catch (err) {
    console.error('Error marking complete:', err)
    alert(`Error: ${err.message || 'Failed to mark booking as complete'}`)
  } finally {
    markingComplete.value = null
  }
}

onMounted(() => {
  loadBookings()
})
</script>
