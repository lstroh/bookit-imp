<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Staff Members</h2>
        <p class="text-sm text-gray-600 mt-1">Manage your team and their service assignments</p>
      </div>
      <button
        v-if="isAdmin"
        @click="openCreateModal"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        + New Staff Member
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Search
          </label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search staff..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @input="debouncedSearch"
          />
        </div>

        <!-- Role Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Role
          </label>
          <select
            v-model="filters.role"
            @change="loadStaff"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="all">All Roles</option>
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Status
          </label>
          <select
            v-model="filters.status"
            @change="loadStaff"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <!-- Service Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Service
          </label>
          <select
            v-model="filters.service_id"
            @change="loadStaff"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="">All Services</option>
            <option
              v-for="service in services"
              :key="service.id"
              :value="service.id"
            >
              {{ service.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12 bg-white rounded-lg shadow">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading staff...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="staff.length === 0" class="text-center py-12 bg-white rounded-lg shadow">
      <span class="text-5xl block mb-2">👥</span>
      <h3 class="text-sm font-medium text-gray-900">No staff members found</h3>
      <p class="mt-1 text-sm text-gray-500">Get started by adding your first staff member.</p>
      <button
        v-if="isAdmin"
        @click="openCreateModal"
        class="mt-4 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        + New Staff Member
      </button>
      <p v-else class="mt-2 text-sm text-gray-500">
        Contact your administrator to add staff members.
      </p>
    </div>

    <!-- Staff Table -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="w-12 px-3 py-3"></th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Staff Member
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Contact
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Role
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Services
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Working Hours
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
            v-for="member in staff"
            :key="member.id"
            class="hover:bg-gray-50 transition-colors"
            :class="{ 'opacity-50': !member.is_active }"
          >
            <!-- Drag Handle -->
            <td class="px-3 py-4">
              <button
                v-if="isAdmin"
                class="cursor-move text-gray-400 hover:text-gray-600"
                title="Drag to reorder"
              >
                ⋮⋮
              </button>
            </td>

            <!-- Staff Member -->
            <td class="px-6 py-4">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                  <img
                    v-if="member.photo_url"
                    :src="member.photo_url"
                    :alt="member.full_name"
                    class="h-10 w-10 rounded-full object-cover"
                  />
                  <div
                    v-else
                    class="h-10 w-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                    :style="{ backgroundColor: getColorForInitials(member.full_name) }"
                  >
                    {{ getInitials(member.full_name) }}
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-900">
                    {{ member.full_name }}
                  </div>
                  <div v-if="member.title" class="text-xs text-gray-500">
                    {{ member.title }}
                  </div>
                  <div class="text-xs text-gray-400 mt-1">
                    Order: {{ member.display_order }}
                  </div>
                </div>
              </div>
            </td>

            <!-- Contact -->
            <td class="px-6 py-4">
              <div class="text-sm text-gray-900">{{ member.email }}</div>
              <div v-if="member.phone" class="text-xs text-gray-500 mt-1">
                {{ member.phone }}
              </div>
            </td>

            <!-- Role -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full"
                :class="member.role === 'admin'
                  ? 'bg-purple-100 text-purple-800'
                  : 'bg-blue-100 text-blue-800'"
              >
                {{ member.role === 'admin' ? 'Admin' : 'Staff' }}
              </span>
            </td>

            <!-- Services -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">
                {{ member.service_count }} service{{ member.service_count !== 1 ? 's' : '' }}
              </div>
            </td>

            <!-- Working Hours -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div v-if="member.has_working_hours" class="flex items-center text-sm text-green-600">
                <span class="mr-1">✓</span>
                Configured
              </div>
              <div v-else class="flex items-center text-sm text-amber-600">
                <span class="mr-1">⚠</span>
                Not configured
              </div>
            </td>

            <!-- Status -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full"
                :class="member.is_active
                  ? 'bg-green-100 text-green-800'
                  : 'bg-gray-100 text-gray-800'"
              >
                {{ member.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <span v-if="!isAdmin" class="text-xs text-gray-400">View only</span>
              <template v-else>
                <button
                  @click="openEditModal(member)"
                  class="text-primary-600 hover:text-primary-900 mr-3"
                >
                  Edit
                </button>
                <button
                  @click="confirmDelete(member)"
                  class="text-red-600 hover:text-red-900"
                >
                  Delete
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Staff Form Modal -->
    <StaffFormModal
      v-if="showFormModal"
      :staff-member="editingStaff"
      @close="closeFormModal"
      @saved="handleStaffSaved"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start mb-4">
          <span class="text-3xl mr-3">⚠</span>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Delete Staff Member</h3>
          </div>
        </div>

        <p class="text-sm text-gray-700 mb-4">
          Are you sure you want to delete <strong>{{ deletingStaff?.full_name }}</strong>?
        </p>

        <div v-if="deletingStaff?.future_bookings_count > 0" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
          <p class="text-sm text-red-800">
            This staff member has <strong>{{ deletingStaff.future_bookings_count }} future booking(s)</strong>.
            Deletion is not allowed. Please reassign or cancel these bookings first, or deactivate the staff member instead.
          </p>
        </div>

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
            v-if="!deletingStaff?.future_bookings_count"
            @click="deleteStaff"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ deleting ? 'Deleting...' : 'Delete Staff Member' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import StaffFormModal from '../components/StaffFormModal.vue'

const api = useApi()

// Check if current user is admin.
const isAdmin = computed(() => {
  return window.BOOKIT_DASHBOARD?.staff?.role === 'admin'
})

// State
const loading = ref(false)
const staff = ref([])
const services = ref([])

// Filters
const filters = ref({
  search: '',
  role: 'all',
  status: 'all',
  service_id: ''
})

// Form modal state
const showFormModal = ref(false)
const editingStaff = ref(null)

// Delete modal state
const showDeleteModal = ref(false)
const deletingStaff = ref(null)
const deleting = ref(false)
const deleteError = ref('')

// Debounce timer
let searchTimeout = null

// Load staff list with current filters.
const loadStaff = async () => {
  loading.value = true

  try {
    const params = new URLSearchParams({
      role: filters.value.role,
      status: filters.value.status
    })

    if (filters.value.search) {
      params.append('search', filters.value.search)
    }

    if (filters.value.service_id) {
      params.append('service_id', filters.value.service_id)
    }

    const response = await api.get(`staff/list?${params.toString()}`)

    if (response.data.success) {
      staff.value = response.data.staff
    }
  } catch (err) {
    console.error('Error loading staff:', err)
  } finally {
    loading.value = false
  }
}

// Load services for the filter dropdown.
const loadServices = async () => {
  try {
    const response = await api.get('services/list?status=active')
    if (response.data.success) {
      services.value = response.data.services
    }
  } catch (err) {
    console.error('Error loading services:', err)
  }
}

// Debounced search handler.
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadStaff()
  }, 500)
}

// Open create modal.
const openCreateModal = () => {
  editingStaff.value = null
  showFormModal.value = true
}

// Open edit modal with staff member data.
const openEditModal = (member) => {
  editingStaff.value = member
  showFormModal.value = true
}

// Close form modal.
const closeFormModal = () => {
  showFormModal.value = false
  editingStaff.value = null
}

// Handle successful save from form modal.
const handleStaffSaved = () => {
  closeFormModal()
  loadStaff()
}

// Open delete confirmation modal.
const confirmDelete = (member) => {
  deletingStaff.value = member
  deleteError.value = ''
  showDeleteModal.value = true
}

// Execute staff deletion.
const deleteStaff = async () => {
  if (!deletingStaff.value) return

  deleting.value = true
  deleteError.value = ''

  try {
    const response = await api.delete(`staff/${deletingStaff.value.id}`)

    if (response.data.success) {
      showDeleteModal.value = false
      loadStaff()
    } else {
      deleteError.value = response.data.message || 'Failed to delete staff member'
    }
  } catch (err) {
    console.error('Error deleting staff:', err)
    deleteError.value = err.message || 'Failed to delete staff member'
  } finally {
    deleting.value = false
  }
}

// Get initials from a full name string.
const getInitials = (fullName) => {
  if (!fullName) return '??'
  const names = fullName.split(' ')
  if (names.length === 1) {
    return names[0].substring(0, 2).toUpperCase()
  }
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

// Generate a consistent color based on name for avatar backgrounds.
const getColorForInitials = (name) => {
  const colors = [
    '#3B82F6',
    '#8B5CF6',
    '#EC4899',
    '#10B981',
    '#F59E0B',
    '#EF4444',
    '#6366F1',
    '#14B8A6'
  ]

  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  return colors[Math.abs(hash) % colors.length]
}

// Lifecycle
onMounted(() => {
  loadStaff()
  loadServices()
})
</script>
