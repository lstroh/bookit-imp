<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Packages</h2>
        <p class="text-sm text-gray-600 mt-1">Manage customer session bundles</p>
      </div>
    </div>

    <div class="mb-4">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg aria-hidden="true" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by package type or customer..."
          class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            id="status-filter"
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadPackages(1)"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="exhausted">Exhausted</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label for="per-page-filter" class="block text-sm font-medium text-gray-700 mb-1">Per page</label>
          <select
            id="per-page-filter"
            v-model.number="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="setPerPage"
          >
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="redeemSuccess" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
      {{ redeemSuccess }}
    </div>

    <div v-if="redeemError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ redeemError }}
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div v-if="loading">
        <TableSkeleton :rows="8" :columns="7" />
      </div>

      <ErrorState
        v-else-if="error"
        title="Failed to load packages"
        :message="errorMessage"
        :show-home="false"
        @retry="loadPackages(pagination.current_page)"
      />

      <div v-else-if="pagedPackages.length === 0" class="p-8 text-center text-sm text-gray-600">
        <p>No packages found.</p>
        <p class="mt-1 text-xs text-gray-500">Customer packages will appear here once customers purchase session bundles.</p>
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchased</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="pkg in pagedPackages" :key="pkg.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <router-link :to="`/customers/${pkg.customer_id}`" class="text-primary-600 hover:text-primary-700 font-medium">
                    {{ getCustomerDisplayName(pkg) }}
                  </router-link>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ pkg.package_type_name || 'Package' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ Number(pkg.sessions_remaining || 0) }} / {{ Number(pkg.sessions_total || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getStatusClass(pkg.status)"
                  >
                    {{ formatStatus(pkg.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatDate(pkg.purchased_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ pkg.expires_at ? formatDate(pkg.expires_at) : 'Never' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button
                    v-if="pkg.status === 'active' && Number(pkg.sessions_remaining || 0) > 0"
                    class="px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                    :disabled="redeemingId === pkg.id"
                    @click="redeemPackage(pkg)"
                  >
                    {{ redeemingId === pkg.id ? 'Redeeming...' : 'Redeem Session' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-200" aria-label="Packages pagination">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
              Showing
              <span class="font-medium">{{ resultsStart }}</span>
              to
              <span class="font-medium">{{ resultsEnd }}</span>
              of
              <span class="font-medium">{{ pagination.total }}</span>
              packages
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
              <button
                @click="goToPage(1)"
                :disabled="pagination.current_page <= 1"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &laquo; First
              </button>
              <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &lsaquo; Prev
              </button>

              <div class="hidden sm:flex items-center gap-1">
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

              <span class="sm:hidden text-sm text-gray-700 px-2">
                {{ pagination.current_page }} / {{ pagination.total_pages }}
              </span>

              <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next &rsaquo;
              </button>
              <button
                @click="goToPage(pagination.total_pages)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Last &raquo;
              </button>
            </div>
          </div>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import TableSkeleton from '../components/TableSkeleton.vue'

const router = useRouter()
const api = useApi()
const currentUser = window.BOOKIT_DASHBOARD?.staff || {}

const isAdmin = computed(() => currentUser.role === 'admin' || currentUser.role === 'bookit_admin')

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const packages = ref([])
const filters = ref({ status: '', per_page: 25 })
const pagination = ref({ total: 0, per_page: 25, current_page: 1, total_pages: 1 })
const searchQuery = ref('')
const redeemingId = ref(null)
const redeemError = ref('')
const redeemSuccess = ref('')
const customerNames = ref({})

let searchTimeout = null

const filteredPackages = computed(() => {
  const term = searchQuery.value.trim().toLowerCase()
  if (!term) {
    return packages.value
  }

  return packages.value.filter((pkg) => {
    const packageType = String(pkg.package_type_name || '').toLowerCase()
    const customerName = String(customerNames.value[pkg.customer_id] || '').toLowerCase()
    const fallbackCustomer = `customer #${pkg.customer_id}`.toLowerCase()

    return packageType.includes(term) || customerName.includes(term) || fallbackCustomer.includes(term)
  })
})

const pagedPackages = computed(() => {
  const start = (pagination.value.current_page - 1) * pagination.value.per_page
  return filteredPackages.value.slice(start, start + pagination.value.per_page)
})

const resultsStart = computed(() => {
  if (pagination.value.total === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const resultsEnd = computed(() => {
  if (pagination.value.total === 0) return 0
  const end = pagination.value.current_page * pagination.value.per_page
  return Math.min(end, pagination.value.total)
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const total = pagination.value.total_pages
  const pages = []

  if (total > 0) pages.push(1)
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i)
  }
  if (total > 1 && !pages.includes(total)) pages.push(total)

  return pages
})

const syncPagination = () => {
  const total = filteredPackages.value.length
  const perPage = Number(filters.value.per_page || 25)
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const currentPage = Math.min(pagination.value.current_page, totalPages)

  pagination.value = {
    total,
    per_page: perPage,
    current_page: currentPage,
    total_pages: totalPages
  }
}

async function hydrateCustomerNamesForPage() {
  const missingIds = [...new Set(
    pagedPackages.value
      .map((pkg) => Number(pkg.customer_id))
      .filter((id) => id > 0 && !customerNames.value[id])
  )]

  if (!missingIds.length) {
    return
  }

  const responses = await Promise.allSettled(
    missingIds.map((id) => api.get(`/customers/${id}`))
  )

  responses.forEach((result, index) => {
    const id = missingIds[index]
    if (result.status !== 'fulfilled') {
      return
    }

    const customer = result.value?.data?.customer
    if (!customer) {
      return
    }

    customerNames.value[id] = customer.full_name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || `Customer #${id}`
  })
}

function getCustomerDisplayName(pkg) {
  return customerNames.value[pkg.customer_id] || `Customer #${pkg.customer_id}`
}

function formatDate(value) {
  if (!value) return 'Never'
  const normalized = String(value).includes(' ') ? String(value).replace(' ', 'T') : `${value}T00:00:00`
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return 'Invalid date'
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatStatus(status) {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

function getStatusClass(status) {
  const map = {
    active: 'bg-green-100 text-green-800',
    exhausted: 'bg-gray-100 text-gray-600',
    expired: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-red-100 text-red-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

async function loadPackages(page = 1) {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  redeemError.value = ''
  redeemSuccess.value = ''

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(filters.value.per_page)
    })
    if (filters.value.status) params.append('status', filters.value.status)

    const response = await api.get(`/customer-packages?${params.toString()}`)
    const responseData = response.data
    const rows = Array.isArray(responseData)
      ? responseData
      : (Array.isArray(responseData?.packages) ? responseData.packages : [])

    packages.value = rows
    pagination.value.current_page = page
    syncPagination()
    await hydrateCustomerNamesForPage()
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
  } finally {
    loading.value = false
  }
}

async function redeemPackage(row) {
  redeemError.value = ''
  redeemSuccess.value = ''

  const input = window.prompt('Redeem one session from this package against booking ID:')
  if (input === null) {
    return
  }

  const bookingId = Number.parseInt(String(input).trim(), 10)
  if (!Number.isInteger(bookingId) || bookingId <= 0) {
    return
  }

  redeemingId.value = row.id
  try {
    await api.post('/package-redemptions', {
      customer_package_id: row.id,
      booking_id: bookingId,
      notes: ''
    })
    redeemSuccess.value = 'Session redeemed successfully.'
    await loadPackages(pagination.value.current_page)
  } catch (err) {
    const code = err.code ? `${err.code}: ` : ''
    redeemError.value = `${code}${err.message || 'Failed to redeem session.'}`
  } finally {
    redeemingId.value = null
  }
}

async function goToPage(page) {
  if (page < 1 || page > pagination.value.total_pages) return
  pagination.value.current_page = page
  await hydrateCustomerNamesForPage()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function setPerPage() {
  pagination.value.current_page = 1
  syncPagination()
  await hydrateCustomerNamesForPage()
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    pagination.value.current_page = 1
    syncPagination()
    await hydrateCustomerNamesForPage()
  }, 400)
})

watch(
  () => filteredPackages.value.length,
  async () => {
    syncPagination()
    await hydrateCustomerNamesForPage()
  }
)

onMounted(() => {
  if (!isAdmin.value) {
    router.push('/')
    return
  }

  loadPackages(1)
})

onBeforeUnmount(() => {
  clearTimeout(searchTimeout)
})
</script>
