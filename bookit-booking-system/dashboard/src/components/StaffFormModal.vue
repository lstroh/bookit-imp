<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">
            {{ isEditing ? 'Edit Staff Member' : 'Add New Staff Member' }}
          </h2>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
          >
            &times;
          </button>
        </div>
      </div>

      <!-- Loading Details -->
      <div v-if="loadingDetails" class="px-6 py-12 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        <p class="mt-2 text-sm text-gray-600">Loading staff details...</p>
      </div>

      <template v-else>
        <!-- Body -->
        <form @submit.prevent="saveStaff" class="px-6 py-6 space-y-6">
          <!-- Error Message -->
          <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-sm text-red-800">{{ errorMessage }}</p>
          </div>

          <!-- Profile Photo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Profile Photo
            </label>
            <div class="flex items-center gap-4">
              <div class="flex-shrink-0">
                <img
                  v-if="formData.photo_url"
                  :src="formData.photo_url"
                  alt="Profile photo"
                  class="h-20 w-20 rounded-full object-cover border-2 border-gray-200"
                />
                <div
                  v-else
                  class="h-20 w-20 rounded-full flex items-center justify-center text-white font-semibold text-xl border-2 border-gray-200"
                  :style="{ backgroundColor: getColorForInitials(formData.first_name + ' ' + formData.last_name) }"
                >
                  {{ getInitials(formData.first_name + ' ' + formData.last_name) }}
                </div>
              </div>
              <div class="flex-1">
                <button
                  type="button"
                  @click="openMediaLibrary"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  {{ formData.photo_url ? 'Change Photo' : 'Upload Photo' }}
                </button>
                <button
                  v-if="formData.photo_url"
                  type="button"
                  @click="formData.photo_url = ''"
                  class="ml-2 px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700"
                >
                  Remove
                </button>
                <p class="text-xs text-gray-500 mt-1">
                  JPG, PNG or GIF. Max 5MB.
                </p>
              </div>
            </div>
          </div>

          <!-- Name -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                First Name *
              </label>
              <input
                v-model="formData.first_name"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="John"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Last Name *
              </label>
              <input
                v-model="formData.last_name"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Doe"
              />
            </div>
          </div>

          <!-- Email and Password -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Email *
              </label>
              <input
                v-model="formData.email"
                type="email"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="john@example.com"
              />
            </div>
            <div v-if="!isEditing">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Password *
              </label>
              <input
                v-model="formData.password"
                type="password"
                :required="!isEditing"
                minlength="8"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Min 8 characters"
              />
            </div>
          </div>

          <!-- Password Reset (Edit Mode Only) -->
          <div v-if="isEditing" class="border border-gray-300 rounded-lg p-4 bg-gray-50">
            <div class="flex items-center justify-between mb-2">
              <h4 class="text-sm font-medium text-gray-900">Password Reset</h4>
              <button
                v-if="!showPasswordReset"
                type="button"
                @click="showPasswordReset = true"
                class="text-sm text-primary-600 hover:text-primary-700"
              >
                Reset Password
              </button>
            </div>

            <div v-if="showPasswordReset" class="space-y-3 mt-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  New Password *
                </label>
                <input
                  v-model="newPassword"
                  type="text"
                  minlength="8"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="Min 8 characters"
                />
                <button
                  type="button"
                  @click="generatePassword"
                  class="text-xs text-primary-600 hover:text-primary-700 mt-1"
                >
                  Generate secure password
                </button>
              </div>

              <div>
                <label class="flex items-center">
                  <input
                    type="checkbox"
                    v-model="sendPasswordEmail"
                    class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">
                    Email new password to staff member
                  </span>
                </label>
              </div>

              <div class="flex gap-2">
                <button
                  type="button"
                  @click="resetPassword"
                  :disabled="!newPassword || newPassword.length < 8 || resettingPassword"
                  class="px-3 py-1.5 text-sm font-medium text-white bg-amber-600 rounded hover:bg-amber-700 disabled:opacity-50"
                >
                  {{ resettingPassword ? 'Resetting...' : 'Reset Password' }}
                </button>
                <button
                  type="button"
                  @click="showPasswordReset = false; newPassword = ''"
                  class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>

          <!-- Phone and Title -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Phone
              </label>
              <input
                v-model="formData.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="01234567890"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Job Title
              </label>
              <input
                v-model="formData.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Senior Stylist"
              />
            </div>
          </div>

          <!-- Bio -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Bio
            </label>
            <textarea
              v-model="formData.bio"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Brief description about this staff member..."
            ></textarea>
          </div>

          <!-- Service Assignments -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Service Assignments
            </label>
            <div v-if="services.length === 0" class="border border-gray-200 rounded-lg p-4 text-center text-sm text-gray-500">
              No services available. Create services first.
            </div>
            <div v-else class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-64 overflow-y-auto">
              <div
                v-for="service in services"
                :key="service.id"
                class="p-3 hover:bg-gray-50"
              >
                <div class="flex items-start">
                  <div class="flex items-center h-5 mt-0.5">
                    <input
                      type="checkbox"
                      :value="service.id"
                      v-model="selectedServices"
                      @change="onServiceToggle(service)"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                    />
                  </div>
                  <div class="ml-3 flex-1">
                    <label class="text-sm font-medium text-gray-900">
                      {{ service.name }}
                    </label>
                    <p class="text-xs text-gray-500">
                      Base price: &pound;{{ parseFloat(service.price).toFixed(2) }}
                    </p>
                    <div v-if="selectedServices.includes(service.id)" class="mt-2">
                      <label class="block text-xs text-gray-600 mb-1">
                        Custom Price (optional)
                      </label>
                      <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                          <span class="absolute left-3 top-2 text-gray-500 text-sm">&pound;</span>
                          <input
                            v-model.number="customPrices[service.id]"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Leave empty for base price"
                            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                          />
                        </div>
                        <button
                          v-if="customPrices[service.id]"
                          type="button"
                          @click="customPrices[service.id] = null"
                          class="text-xs text-red-600 hover:text-red-700"
                        >
                          Clear
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              Select services this staff member can provide. Set custom prices to override the base service price.
            </p>
          </div>

          <!-- Role, Status, and Display Order -->
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Role *
              </label>
              <select
                v-model="formData.role"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Display Order
              </label>
              <input
                v-model.number="formData.display_order"
                type="number"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center">
                <input
                  type="checkbox"
                  v-model="formData.is_active"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="ml-2 text-sm font-medium text-gray-700">
                  Active
                </span>
              </label>
            </div>
          </div>

          <!-- Google Calendar ID -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Google Calendar ID (Optional)
            </label>
            <input
              v-model="formData.google_calendar_id"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="calendar@gmail.com"
            />
            <p class="text-xs text-gray-500 mt-1">
              For Google Calendar sync (future feature)
            </p>
          </div>

          <!-- Working Hours Info (editing only) -->
          <div v-if="isEditing && staffDetails?.has_working_hours" class="bg-blue-50 border border-blue-200 rounded p-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-blue-800">
                Working hours are <strong>configured</strong>.
              </p>
              <router-link
                :to="`/staff/${staffMember.id}/hours`"
                @click="$emit('close')"
                class="text-sm font-medium text-blue-600 hover:text-blue-700 underline"
              >
                Edit Working Hours &rarr;
              </router-link>
            </div>
          </div>
          <div v-else-if="isEditing && staffDetails && !staffDetails.has_working_hours" class="bg-amber-50 border border-amber-200 rounded p-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-amber-800">
                Working hours <strong>not configured</strong>.
                This staff member won't appear in booking availability.
              </p>
              <router-link
                :to="`/staff/${staffMember.id}/hours`"
                @click="$emit('close')"
                class="text-sm font-medium text-amber-600 hover:text-amber-700 underline whitespace-nowrap ml-3"
              >
                Configure Now &rarr;
              </router-link>
            </div>
          </div>

          <!-- Bookings Info (editing only) -->
          <div v-if="isEditing && staffDetails?.future_bookings_count > 0" class="bg-purple-50 border border-purple-200 rounded p-3">
            <p class="text-sm text-purple-800">
              This staff member has <strong>{{ staffDetails.future_bookings_count }} future booking(s)</strong>.
            </p>
          </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 sticky bottom-0">
          <button
            @click="$emit('close')"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            @click="saveStaff"
            :disabled="saving || !isValid"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ saving ? 'Saving...' : (isEditing ? 'Update Staff Member' : 'Create Staff Member') }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const props = defineProps({
  staffMember: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

// State
const saving = ref(false)
const loadingDetails = ref(false)
const errorMessage = ref('')
const services = ref([])
const selectedServices = ref([])
const customPrices = ref({})
const staffDetails = ref(null)

// Password reset state
const showPasswordReset = ref(false)
const newPassword = ref('')
const sendPasswordEmail = ref(true)
const resettingPassword = ref(false)

const formData = ref({
  email: '',
  password: '',
  first_name: '',
  last_name: '',
  phone: '',
  photo_url: '',
  bio: '',
  title: '',
  role: 'staff',
  google_calendar_id: '',
  is_active: true,
  display_order: 0
})

// Computed
const isEditing = computed(() => !!props.staffMember)

const isValid = computed(() => {
  const basicValid = formData.value.email &&
    formData.value.first_name &&
    formData.value.last_name &&
    formData.value.role

  if (isEditing.value) {
    return basicValid
  }
  return basicValid && formData.value.password && formData.value.password.length >= 8
})

// Load available services for assignment checkboxes.
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

// Load full staff details (includes service_assignments).
const loadStaffDetails = async (staffId) => {
  loadingDetails.value = true

  try {
    const response = await api.get(`staff/${staffId}`)
    if (response.data.success) {
      staffDetails.value = response.data.staff
      populateForm(response.data.staff)
    }
  } catch (err) {
    console.error('Error loading staff details:', err)
    errorMessage.value = 'Failed to load staff details.'
  } finally {
    loadingDetails.value = false
  }
}

// Populate form fields from staff data.
const populateForm = (member) => {
  formData.value = {
    email: member.email || '',
    password: '',
    first_name: member.first_name || '',
    last_name: member.last_name || '',
    phone: member.phone || '',
    photo_url: member.photo_url || '',
    bio: member.bio || '',
    title: member.title || '',
    role: member.role || 'staff',
    google_calendar_id: member.google_calendar_id || '',
    is_active: member.is_active ?? true,
    display_order: member.display_order || 0
  }

  if (member.service_assignments) {
    selectedServices.value = member.service_assignments.map(a => a.service_id)
    customPrices.value = {}
    member.service_assignments.forEach(assignment => {
      if (assignment.custom_price) {
        customPrices.value[assignment.service_id] = assignment.custom_price
      }
    })
  }
}

// Handle service checkbox toggle.
const onServiceToggle = (service) => {
  if (!selectedServices.value.includes(service.id)) {
    delete customPrices.value[service.id]
  }
}

// Open WordPress media library for photo selection.
const openMediaLibrary = () => {
  if (typeof wp !== 'undefined' && wp.media) {
    const mediaFrame = wp.media({
      title: 'Select Profile Photo',
      button: { text: 'Use this photo' },
      multiple: false,
      library: { type: 'image' }
    })

    mediaFrame.on('select', () => {
      const attachment = mediaFrame.state().get('selection').first().toJSON()
      formData.value.photo_url = attachment.url
    })

    mediaFrame.open()
  } else {
    const url = prompt('WordPress media library not available.\nEnter image URL manually:')
    if (url) {
      formData.value.photo_url = url
    }
  }
}

// Get initials from a full name string.
const getInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) {
    return names[0].substring(0, 2).toUpperCase()
  }
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

// Generate a consistent color based on name for avatar backgrounds.
const getColorForInitials = (name) => {
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

// Generate a random 12-character password.
const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%'
  let password = ''
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newPassword.value = password
}

// Reset staff member password.
const resetPassword = async () => {
  if (!newPassword.value || newPassword.value.length < 8) return

  resettingPassword.value = true

  try {
    const response = await api.post(`staff/${props.staffMember.id}/reset-password`, {
      new_password: newPassword.value,
      send_email: sendPasswordEmail.value
    })

    if (response.data.success) {
      alert('Password reset successfully!' + (sendPasswordEmail.value ? ' Email sent to staff member.' : ''))
      showPasswordReset.value = false
      newPassword.value = ''
    } else {
      throw new Error(response.data.message || 'Failed to reset password')
    }
  } catch (err) {
    console.error('Error resetting password:', err)
    alert(`Error: ${err.message}`)
  } finally {
    resettingPassword.value = false
  }
}

// Save staff member (create or update).
const saveStaff = async () => {
  if (!isValid.value || saving.value) return

  saving.value = true
  errorMessage.value = ''

  try {
    const service_assignments = selectedServices.value.map(serviceId => ({
      service_id: serviceId,
      custom_price: customPrices.value[serviceId] || null
    }))

    const payload = {
      email: formData.value.email,
      first_name: formData.value.first_name,
      last_name: formData.value.last_name,
      phone: formData.value.phone,
      photo_url: formData.value.photo_url,
      bio: formData.value.bio,
      title: formData.value.title,
      role: formData.value.role,
      google_calendar_id: formData.value.google_calendar_id,
      is_active: formData.value.is_active,
      display_order: formData.value.display_order,
      service_assignments: service_assignments
    }

    if (!isEditing.value) {
      payload.password = formData.value.password
    }

    let response
    if (isEditing.value) {
      response = await api.put(`staff/${props.staffMember.id}`, payload)
    } else {
      response = await api.post('staff/create', payload)
    }

    if (response.data.success) {
      emit('saved', response.data.staff)
    } else {
      errorMessage.value = response.data.message || 'Failed to save staff member'
    }
  } catch (err) {
    console.error('Error saving staff:', err)

    if (err.message && err.message.includes('email already exists')) {
      errorMessage.value = 'A staff member with this email already exists. Please use a different email.'
    } else {
      errorMessage.value = err.message || 'Failed to save staff member'
    }
  } finally {
    saving.value = false
  }
}

// Initialize: load services and staff details if editing.
onMounted(async () => {
  await loadServices()

  if (props.staffMember) {
    await loadStaffDetails(props.staffMember.id)
  }
})
</script>
