<template>
  <div>
    <!-- Header with Actions -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">All Bookings</h2>
        <p class="text-sm text-gray-600 mt-1">
          Manage all appointments
        </p>
      </div>
      <button
        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium"
        @click="createBooking"
      >
        + New Booking
      </button>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
           :class="isAdmin ? 'lg:grid-cols-5' : 'lg:grid-cols-4'">
        <!-- Date From -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            From Date
          </label>
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          />
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            To Date
          </label>
          <input
            v-model="filters.date_to"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          />
        </div>

        <!-- Staff Filter (Admin Only) -->
        <div v-if="isAdmin">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Staff Member
          </label>
          <select
            v-model="filters.staff_id"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          >
            <option value="">All Staff</option>
            <option v-for="staff in staffList" :key="staff.id" :value="staff.id">
              {{ staff.name }}
            </option>
          </select>
        </div>

        <!-- Service Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Service
          </label>
          <select
            v-model="filters.service_id"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          >
            <option value="">All Services</option>
            <option v-for="service in servicesList" :key="service.id" :value="service.id">
              {{ service.name }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Status
          </label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="pending_payment">Pending Payment</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
          </select>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="mt-4">
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by customer name or email..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @input="onSearchInput"
          />
          <span class="absolute left-3 top-2.5 text-gray-400">&#x1F50D;</span>
          <button
            v-if="searchQuery || hasActiveFilters"
            @click="clearFilters"
            class="absolute right-3 top-2 text-sm text-gray-500 hover:text-gray-700"
          >
            Clear All
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12 bg-white rounded-lg shadow">
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
      <div class="text-6xl mb-4">&#x1F4CB;</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">
        No bookings found
      </h3>
      <p class="text-sm text-gray-600 mb-4">
        {{ hasActiveFilters ? 'Try adjusting your filters' : 'No bookings have been created yet' }}
      </p>
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="text-sm text-primary-600 hover:text-primary-700 underline"
      >
        Clear filters
      </button>
    </div>

    <!-- Bookings Table -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Date &amp; Time
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Customer
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Service
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Staff
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Amount
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="booking in bookings"
              :key="booking.id"
              class="hover:bg-gray-50 cursor-pointer transition-colors"
              @click="viewBooking(booking)"
            >
              <!-- Date & Time -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ formatDate(booking.booking_date) }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.start_time }} - {{ booking.end_time }}
                </div>
              </td>

              <!-- Customer -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ booking.customer_name }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.customer_email }}
                </div>
              </td>

              <!-- Service -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  {{ booking.service_name }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.duration }} min
                </div>
              </td>

              <!-- Staff -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ booking.staff_name }}
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </td>

              <!-- Amount -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  &pound;{{ booking.total_price.toFixed(2) }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ getPaymentLabel(booking) }}
                </div>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click.stop="viewBooking(booking)"
                  class="text-primary-600 hover:text-primary-900"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <!-- Results Info -->
          <div class="text-sm text-gray-700">
            Showing
            <span class="font-medium">{{ resultsStart }}</span>
            to
            <span class="font-medium">{{ resultsEnd }}</span>
            of
            <span class="font-medium">{{ pagination.total }}</span>
            bookings
          </div>

          <!-- Pagination Controls -->
          <div class="flex items-center gap-2">
            <button
              @click="goToPage(1)"
              :disabled="!pagination.has_prev"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              &laquo; First
            </button>
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="!pagination.has_prev"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              &lsaquo; Prev
            </button>

            <!-- Page Numbers -->
            <div class="flex items-center gap-1">
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="goToPage(page)"
                class="px-3 py-2 text-sm font-medium rounded-lg"
                :class="page === pagination.current_page
                  ? 'bg-primary-600 text-white'
                  : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'"
              >
                {{ page }}
              </button>
            </div>

            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="!pagination.has_next"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next &rsaquo;
            </button>
            <button
              @click="goToPage(pagination.total_pages)"
              :disabled="!pagination.has_next"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Last &raquo;
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Booking Creation Modal -->
    <BookingModal
      v-if="showBookingModal"
      @close="closeBookingModal"
      @created="handleBookingCreated"
    />

    <!-- Booking View/Edit Modal -->
    <BookingViewModal
      v-if="showViewModal && selectedBookingId"
      :booking-id="selectedBookingId"
      @close="closeViewModal"
      @updated="handleBookingUpdated"
      @cancelled="handleBookingCancelled"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import BookingModal from '../components/BookingModal.vue'
import BookingViewModal from '../components/BookingViewModal.vue'

const api = useApi()

// Get current user role
const currentUser = window.BOOKIT_DASHBOARD.staff
const isAdmin = computed(() => currentUser.role === 'admin')

// State
const loading = ref(true)
const error = ref(null)
const bookings = ref([])
const staffList = ref([])
const servicesList = ref([])
const searchQuery = ref('')
let searchTimeout = null

// Filters
const filters = ref({
  date_from: '',
  date_to: '',
  staff_id: '',
  service_id: '',
  status: '',
})

// Pagination
const pagination = ref({
  total: 0,
  per_page: 20,
  current_page: 1,
  total_pages: 1,
  has_next: false,
  has_prev: false,
})

// Computed
const hasActiveFilters = computed(() => {
  return filters.value.date_from ||
         filters.value.date_to ||
         filters.value.staff_id ||
         filters.value.service_id ||
         filters.value.status ||
         searchQuery.value
})

const resultsStart = computed(() => {
  if (bookings.value.length === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const resultsEnd = computed(() => {
  const end = pagination.value.current_page * pagination.value.per_page
  return Math.min(end, pagination.value.total)
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const total = pagination.value.total_pages
  const pages = []

  // Always show first page
  if (total > 0) pages.push(1)

  // Show pages around current
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i)
  }

  // Always show last page
  if (total > 1 && !pages.includes(total)) pages.push(total)

  return pages
})

// Methods
const loadBookings = async (page = 1) => {
  loading.value = true
  error.value = null

  try {
    // Build query params
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.per_page.toString(),
    })

    if (filters.value.date_from) params.append('date_from', filters.value.date_from)
    if (filters.value.date_to) params.append('date_to', filters.value.date_to)
    if (filters.value.staff_id) params.append('staff_id', filters.value.staff_id)
    if (filters.value.service_id) params.append('service_id', filters.value.service_id)
    if (filters.value.status) params.append('status', filters.value.status)
    if (searchQuery.value) params.append('search', searchQuery.value)

    const response = await api.get(`/bookings?${params.toString()}`)

    if (response.data.success) {
      bookings.value = response.data.bookings
      pagination.value = response.data.pagination
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

const loadFilterData = async () => {
  try {
    // Load staff list
    const staffResponse = await api.get('/staff/list')
    if (staffResponse.data.success) {
      staffList.value = staffResponse.data.staff
    }

    // Load services list
    const servicesResponse = await api.get('/services/list')
    if (servicesResponse.data.success) {
      servicesList.value = servicesResponse.data.services
    }
  } catch (err) {
    console.error('Error loading filter data:', err)
  }
}

const applyFilters = () => {
  loadBookings(1) // Reset to page 1 when filters change
}

const onSearchInput = () => {
  // Debounce search
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadBookings(1)
  }, 500)
}

const clearFilters = () => {
  filters.value = {
    date_from: '',
    date_to: '',
    staff_id: '',
    service_id: '',
    status: '',
  }
  searchQuery.value = ''
  loadBookings(1)
}

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return
  loadBookings(page)
  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// View/Edit modal state.
const selectedBookingId = ref(null)
const showViewModal = ref(false)

const viewBooking = (booking) => {
  selectedBookingId.value = booking.id
  showViewModal.value = true
}

const handleBookingUpdated = (updatedBooking) => {
  // Refresh bookings list.
  loadBookings(pagination.value.current_page)
  showViewModal.value = false
}

const handleBookingCancelled = (bookingId) => {
  // Refresh bookings list.
  loadBookings(pagination.value.current_page)
  showViewModal.value = false
}

const closeViewModal = () => {
  showViewModal.value = false
  selectedBookingId.value = null
}

// Create modal state.
const showBookingModal = ref(false)

const createBooking = () => {
  showBookingModal.value = true
}

const closeBookingModal = () => {
  showBookingModal.value = false
}

const handleBookingCreated = (booking) => {
  // Refresh bookings list
  loadBookings(pagination.value.current_page)
  showBookingModal.value = false
  alert(`\u2713 Booking created successfully!\n\nID: ${booking.id}\nCustomer: ${booking.customer_name}`)
}

const formatDate = (dateString) => {
  const date = new Date(dateString + 'T00:00:00') // Force local timezone
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}

const formatStatus = (status) => {
  const labels = {
    'pending': 'Pending',
    'pending_payment': 'Pending Payment',
    'confirmed': 'Confirmed',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'no_show': 'No Show'
  }
  return labels[status] || status
}

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

const getPaymentLabel = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  if (paid > total && total > 0) {
    // Overpayment (tip included).
    const tip = paid - total
    return `\u00A3${paid.toFixed(2)} paid (incl. \u00A3${tip.toFixed(2)} tip)`
  }
  if (paid >= total && total > 0) {
    // Fully paid (exact amount).
    return `\u00A3${paid.toFixed(2)} paid in full`
  }
  if (paid > 0) {
    // Partially paid.
    return `\u00A3${paid.toFixed(2)} paid, \u00A3${(total - paid).toFixed(2)} due`
  }
  if (booking.payment_method === 'pay_on_arrival') {
    return 'Pay on arrival'
  }
  return `${formatPaymentMethod(booking.payment_method)} - Unpaid`
}

const formatPaymentMethod = (method) => {
  const labels = {
    'pay_on_arrival': 'Pay on Arrival',
    'cash': 'Cash',
    'card_external': 'Card',
    'check': 'Check',
    'complimentary': 'Complimentary',
    'stripe': 'Stripe'
  }
  return labels[method] || method || 'Unknown'
}

// Lifecycle
onMounted(() => {
  loadFilterData()
  loadBookings()
})
</script>
