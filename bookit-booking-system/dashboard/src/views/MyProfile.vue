<template>
  <div class="max-w-4xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading profile...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
      </div>
      <div v-if="saveError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>

      <!-- Profile Information Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Profile Information</h2>
          <p class="text-sm text-gray-500 mt-1">
            Update your personal details and profile photo
          </p>
        </div>

        <form @submit.prevent="saveProfile" class="px-4 sm:px-6 py-6 space-y-6">
          <!-- Profile Photo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Profile Photo
            </label>
            <div class="flex items-center gap-4">
              <div class="flex-shrink-0">
                <img
                  v-if="profile.photo_url"
                  :src="profile.photo_url"
                  alt="Profile photo"
                  class="h-20 w-20 rounded-full object-cover border-2 border-gray-200"
                />
                <div
                  v-else
                  class="h-20 w-20 rounded-full flex items-center justify-center text-white font-semibold text-xl border-2 border-gray-200"
                  :style="{ backgroundColor: getColorForInitials(profile.first_name + ' ' + profile.last_name) }"
                >
                  {{ getInitials(profile.first_name + ' ' + profile.last_name) }}
                </div>
              </div>

              <div class="flex-1">
                <button
                  type="button"
                  @click="openMediaLibrary"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  {{ profile.photo_url ? 'Change Photo' : 'Upload Photo' }}
                </button>
                <button
                  v-if="profile.photo_url"
                  type="button"
                  @click="profile.photo_url = ''"
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="profile-first-name" class="block text-sm font-medium text-gray-700 mb-1">
                First Name *
              </label>
              <input
                id="profile-first-name"
                v-model="profile.first_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label for="profile-last-name" class="block text-sm font-medium text-gray-700 mb-1">
                Last Name *
              </label>
              <input
                id="profile-last-name"
                v-model="profile.last_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <!-- Email (with password verification if changed) -->
          <div>
            <label for="profile-email" class="block text-sm font-medium text-gray-700 mb-1">
              Email Address *
            </label>
            <input
              id="profile-email"
              v-model="profile.email"
              type="email"
              required
              aria-required="true"
              @input="emailChanged = profile.email !== originalEmail"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />

            <div v-if="emailChanged" class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-4">
              <label class="block text-sm font-medium text-amber-900 mb-2">
                Confirm Current Password *
              </label>
              <input
                v-model="emailPasswordConfirm"
                type="password"
                placeholder="Enter your current password"
                class="w-full px-3 py-2 border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
              />
              <p class="text-xs text-amber-700 mt-2">
                For security, we need to verify your password before changing your email address.
              </p>
            </div>
          </div>

          <!-- Phone and Title -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="profile-phone" class="block text-sm font-medium text-gray-700 mb-1">
                Phone
              </label>
              <input
                id="profile-phone"
                v-model="profile.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label for="profile-title" class="block text-sm font-medium text-gray-700 mb-1">
                Job Title
              </label>
              <input
                id="profile-title"
                v-model="profile.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <!-- Bio -->
          <div>
            <label for="profile-bio" class="block text-sm font-medium text-gray-700 mb-1">
              Bio
            </label>
            <textarea
              id="profile-bio"
              v-model="profile.bio"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Brief description about yourself..."
            ></textarea>
          </div>

          <!-- Role (Read-Only) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Role
            </label>
            <div class="flex items-center gap-2">
              <span
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg"
                :class="profile.role === 'admin'
                  ? 'bg-purple-100 text-purple-800'
                  : 'bg-blue-100 text-blue-800'"
              >
                {{ profile.role === 'admin' ? 'Admin' : 'Staff' }}
              </span>
              <p class="text-xs text-gray-500">
                Contact an administrator to change your role
              </p>
            </div>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="savingProfile"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ savingProfile ? 'Saving...' : 'Save Profile' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Change Password Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
          <p class="text-sm text-gray-500 mt-1">
            Update your password to keep your account secure
          </p>
        </div>

        <form @submit.prevent="changePassword" class="px-4 sm:px-6 py-6 space-y-4">
          <!-- Current Password -->
          <div>
            <label for="current-password" class="block text-sm font-medium text-gray-700 mb-1">
              Current Password *
            </label>
            <input
              id="current-password"
              v-model="passwordForm.current_password"
              type="password"
              required
              aria-required="true"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': passwordError }"
            />
          </div>

          <!-- New Password -->
          <div>
            <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1">
              New Password *
            </label>
            <input
              id="new-password"
              v-model="passwordForm.new_password"
              type="password"
              required
              aria-required="true"
              minlength="8"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">
              Minimum 8 characters
            </p>
          </div>

          <!-- Confirm New Password -->
          <div>
            <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1">
              Confirm New Password *
            </label>
            <input
              id="confirm-password"
              v-model="passwordForm.confirm_password"
              type="password"
              required
              aria-required="true"
              minlength="8"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': passwordMismatch }"
            />
            <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1">
              Passwords do not match
            </p>
          </div>

          <!-- Password Error -->
          <div v-if="passwordError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ passwordError }}</p>
          </div>

          <!-- Password Success -->
          <div v-if="passwordSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
            <p class="text-sm text-green-800">&#10003; {{ passwordSuccess }}</p>
          </div>

          <!-- Change Password Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="changingPassword || passwordMismatch"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50"
            >
              {{ changingPassword ? 'Changing...' : 'Change Password' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const loading = ref(false)
const savingProfile = ref(false)
const changingPassword = ref(false)
const saveSuccess = ref('')
const saveError = ref('')
const passwordSuccess = ref('')
const passwordError = ref('')
const originalEmail = ref('')
const emailChanged = ref(false)
const emailPasswordConfirm = ref('')

const profile = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  title: '',
  bio: '',
  photo_url: '',
  role: ''
})

const passwordForm = ref({
  current_password: '',
  new_password: '',
  confirm_password: ''
})

const passwordMismatch = computed(() => {
  return passwordForm.value.new_password &&
         passwordForm.value.confirm_password &&
         passwordForm.value.new_password !== passwordForm.value.confirm_password
})

const loadProfile = async () => {
  loading.value = true

  try {
    const response = await api.get('profile')

    if (response.data.success) {
      profile.value = response.data.profile
      originalEmail.value = response.data.profile.email
    }
  } catch (err) {
    saveError.value = 'Failed to load profile.'
  } finally {
    loading.value = false
  }
}

const saveProfile = async () => {
  saveSuccess.value = ''
  saveError.value = ''

  if (emailChanged.value) {
    if (!emailPasswordConfirm.value) {
      saveError.value = 'Please enter your current password to change your email address.'
      return
    }

    try {
      await api.post('profile/verify-password', {
        password: emailPasswordConfirm.value
      })
    } catch (err) {
      saveError.value = 'Current password is incorrect. Email not changed.'
      return
    }
  }

  savingProfile.value = true

  try {
    const response = await api.put('profile', {
      first_name: profile.value.first_name,
      last_name: profile.value.last_name,
      email: profile.value.email,
      phone: profile.value.phone,
      title: profile.value.title,
      bio: profile.value.bio,
      photo_url: profile.value.photo_url
    })

    if (response.data.success) {
      saveSuccess.value = response.data.message
      profile.value = response.data.profile
      originalEmail.value = profile.value.email
      emailChanged.value = false
      emailPasswordConfirm.value = ''

      setTimeout(() => {
        saveSuccess.value = ''
      }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save profile'
    }
  } catch (err) {
    if (err.code === 'duplicate_email') {
      saveError.value = 'This email is already in use by another staff member.'
    } else {
      saveError.value = err.message || 'Failed to save profile'
    }
  } finally {
    savingProfile.value = false
  }
}

const changePassword = async () => {
  if (passwordMismatch.value) return

  changingPassword.value = true
  passwordSuccess.value = ''
  passwordError.value = ''

  try {
    const response = await api.post('profile/change-password', {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password
    })

    if (response.data.success) {
      passwordSuccess.value = response.data.message

      passwordForm.value = {
        current_password: '',
        new_password: '',
        confirm_password: ''
      }

      setTimeout(() => {
        passwordSuccess.value = ''
      }, 5000)
    } else {
      passwordError.value = response.data.message || 'Failed to change password'
    }
  } catch (err) {
    if (err.code === 'invalid_password') {
      passwordError.value = 'Current password is incorrect.'
    } else {
      passwordError.value = err.message || 'Failed to change password'
    }
  } finally {
    changingPassword.value = false
  }
}

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
      profile.value.photo_url = attachment.url
    })

    mediaFrame.open()
  } else {
    const url = prompt('WordPress media library not available.\nEnter image URL manually:')
    if (url) {
      profile.value.photo_url = url
    }
  }
}

const getInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) return names[0].substring(0, 2).toUpperCase()
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

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

onMounted(() => {
  loadProfile()
})
</script>
