<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Services</h2>
        <p class="text-sm text-gray-600 mt-1">Manage your service offerings</p>
      </div>
      <button
        @click="openCreateModal"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        + New Service
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Search
          </label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search services..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @input="debouncedSearch"
          />
        </div>

        <!-- Category Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Category
          </label>
          <select
            v-model="filters.category_id"
            @change="loadServices(1)"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="">All Categories</option>
            <option
              v-for="category in categories"
              :key="category.id"
              :value="category.id"
            >
              {{ category.name }}
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
            @change="loadServices(1)"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12 bg-white rounded-lg shadow">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading services...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="services.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
      <div class="text-6xl mb-4">&#x2702;&#xFE0F;</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No services found</h3>
      <p class="text-sm text-gray-600 mb-4">Get started by creating a new service.</p>
      <button
        @click="openCreateModal"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        + New Service
      </button>
    </div>

    <!-- Services Table -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-12 px-3 py-3"></th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Service
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Categories
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Duration
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Price
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Deposit
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Buffer
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="service in services"
              :key="service.id"
              class="hover:bg-gray-50 transition-colors"
              :class="{ 'opacity-50': !service.is_active }"
            >
              <!-- Drag Handle -->
              <td class="px-3 py-4">
                <span
                  class="cursor-move text-gray-400 hover:text-gray-600 select-none"
                  title="Drag to reorder"
                >&#x2807;&#x2807;</span>
              </td>

              <!-- Service Name -->
              <td class="px-6 py-4">
                <div class="text-sm font-medium text-gray-900">
                  {{ service.name }}
                </div>
                <div v-if="service.description" class="text-xs text-gray-500 mt-1 line-clamp-1">
                  {{ service.description }}
                </div>
                <div class="text-xs text-gray-400 mt-1">
                  Order: {{ service.display_order }}
                </div>
              </td>

              <!-- Categories -->
              <td class="px-6 py-4">
                <div v-if="service.categories && service.categories.length > 0" class="flex flex-wrap gap-1">
                  <span
                    v-for="category in service.categories"
                    :key="category.id"
                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800"
                  >
                    {{ category.name }}
                  </span>
                </div>
                <span v-else class="text-xs text-gray-400">No categories</span>
              </td>

              <!-- Duration -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ service.duration }} min</div>
              </td>

              <!-- Price -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  &pound;{{ parseFloat(service.price).toFixed(2) }}
                </div>
              </td>

              <!-- Deposit -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="service.deposit_amount" class="text-sm text-gray-900">
                  <template v-if="service.deposit_type === 'percentage'">
                    {{ parseFloat(service.deposit_amount).toFixed(0) }}%
                  </template>
                  <template v-else>
                    &pound;{{ parseFloat(service.deposit_amount).toFixed(2) }}
                  </template>
                  <span class="text-xs text-gray-500">({{ service.deposit_type }})</span>
                </div>
                <span v-else class="text-xs text-gray-400">None</span>
              </td>

              <!-- Buffer -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-xs text-gray-600">
                  <div v-if="service.buffer_before > 0">Before: {{ service.buffer_before }}m</div>
                  <div v-if="service.buffer_after > 0">After: {{ service.buffer_after }}m</div>
                  <span v-if="service.buffer_before === 0 && service.buffer_after === 0" class="text-gray-400">
                    None
                  </span>
                </div>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-0.5 text-xs font-medium rounded-full"
                  :class="service.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-100 text-gray-800'"
                >
                  {{ service.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="openEditModal(service)"
                  class="text-primary-600 hover:text-primary-900 mr-3"
                >
                  Edit
                </button>
                <button
                  @click="confirmDelete(service)"
                  class="text-red-600 hover:text-red-900"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total_pages > 1" class="bg-gray-50 px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Showing
            <span class="font-medium">{{ resultsStart }}</span>
            to
            <span class="font-medium">{{ resultsEnd }}</span>
            of
            <span class="font-medium">{{ pagination.total }}</span>
            services
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              &lsaquo; Prev
            </button>
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="changePage(page)"
              class="px-3 py-2 text-sm font-medium rounded-lg"
              :class="page === pagination.current_page
                ? 'bg-primary-600 text-white'
                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.total_pages"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next &rsaquo;
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Service Form Modal -->
    <ServiceFormModal
      v-if="showFormModal"
      :service="editingService"
      :categories="categories"
      @close="closeFormModal"
      @saved="handleServiceSaved"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start mb-4">
          <span class="text-3xl mr-3">&#x26A0;&#xFE0F;</span>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Delete Service</h3>
          </div>
        </div>

        <p class="text-sm text-gray-700 mb-6">
          Are you sure you want to delete <strong>{{ deletingService?.name }}</strong>?
          This action cannot be undone.
        </p>

        <div v-if="deleteError" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
          <p class="text-sm text-red-800">{{ deleteError }}</p>
        </div>

        <div class="flex justify-end gap-2">
          <button
            @click="showDeleteModal = false; deleteError = ''"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="deleteService"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ deleting ? 'Deleting...' : 'Delete Service' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import ServiceFormModal from '../components/ServiceFormModal.vue'

const api = useApi()

// State
const loading = ref(true)
const services = ref([])
const categories = ref([])
const pagination = ref({
  total: 0,
  per_page: 50,
  current_page: 1,
  total_pages: 1
})

// Filters
const filters = ref({
  search: '',
  category_id: '',
  status: 'all'
})

// Modal state
const showFormModal = ref(false)
const editingService = ref(null)
const showDeleteModal = ref(false)
const deletingService = ref(null)
const deleting = ref(false)
const deleteError = ref('')

// Debounce timer
let searchTimeout = null

// Computed
const resultsStart = computed(() => {
  if (services.value.length === 0) return 0
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

  // Show max 5 page numbers
  let start = Math.max(1, current - 2)
  let end = Math.min(total, start + 4)

  // Adjust start if near the end
  if (end - start < 4) {
    start = Math.max(1, end - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})

// Methods
const loadServices = async (page = 1) => {
  loading.value = true

  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.per_page.toString(),
      status: filters.value.status
    })

    if (filters.value.search) {
      params.append('search', filters.value.search)
    }

    if (filters.value.category_id) {
      params.append('category_id', filters.value.category_id)
    }

    const response = await api.get(`/services/list?${params.toString()}`)

    if (response.data.success) {
      services.value = response.data.services
      pagination.value = response.data.pagination
    }
  } catch (err) {
    console.error('Error loading services:', err)
  } finally {
    loading.value = false
  }
}

const loadCategories = async () => {
  try {
    const response = await api.get('categories/list')
    console.log('Categories API response:', response.data)
    if (response.data.success) {
      categories.value = response.data.categories
      console.log('Loaded categories:', categories.value)
    }
  } catch (err) {
    console.error('Error loading categories:', err)
  }
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadServices(1)
  }, 500)
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.total_pages) {
    loadServices(page)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const openCreateModal = () => {
  editingService.value = null
  showFormModal.value = true
}

const openEditModal = (service) => {
  editingService.value = service
  showFormModal.value = true
}

const closeFormModal = () => {
  showFormModal.value = false
  editingService.value = null
}

const handleServiceSaved = () => {
  closeFormModal()
  loadServices(pagination.value.current_page)
}

const confirmDelete = (service) => {
  deletingService.value = service
  deleteError.value = ''
  showDeleteModal.value = true
}

const deleteService = async () => {
  if (!deletingService.value) return

  deleting.value = true
  deleteError.value = ''

  try {
    const response = await api.delete(`/services/${deletingService.value.id}`)

    if (response.data.success) {
      showDeleteModal.value = false
      deletingService.value = null
      loadServices(pagination.value.current_page)
    } else {
      deleteError.value = response.data.message || 'Failed to delete service'
    }
  } catch (err) {
    console.error('Error deleting service:', err)
    deleteError.value = err.message || 'Failed to delete service'
  } finally {
    deleting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await loadCategories()
  console.log('Categories after load:', categories.value)
  loadServices()
})
</script>
